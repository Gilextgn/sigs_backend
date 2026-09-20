<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\AcademicYears\Models\AcademicYear;
use Modules\Platform\Models\School;
use Modules\SchoolClasses\Models\SchoolCycle;
use Modules\Users\Models\Role;
use Tests\TestCase;

/**
 * Le propriétaire de la plateforme crée des écoles, les suspend et les
 * réactive — sans jamais toucher à leurs données — et chaque école ne voit
 * strictement que les siennes.
 */
class MultiSchoolTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function owner(): User
    {
        return User::create([
            'school_id' => null,
            'role_id' => Role::where('code', 'platform_owner')->value('id'),
            'full_name' => 'Propriétaire',
            'email' => 'owner'.uniqid().'@sigs.test',
            'password' => 'motdepasse',
            'status' => 'active',
        ]);
    }

    /** Crée une école par l'API du propriétaire et renvoie [école, admin, mot de passe]. */
    private function createSchool(string $name, array $extra = []): array
    {
        $email = 'admin'.uniqid().'@ecole.test';

        $response = $this->actingAs($this->owner())->postJson('/api/platform/schools', [
            'name' => $name,
            'admin_name' => 'Admin '.$name,
            'admin_email' => $email,
            ...$extra,
        ])->assertCreated();

        return [
            School::findOrFail($response->json('id')),
            User::where('email', $email)->firstOrFail(),
            $response->json('temporary_password'),
        ];
    }

    /** Se connecte comme le ferait le navigateur (auth:sanctum change le guard par défaut de l'instance de test). */
    private function loginAs(User $user, string $password)
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        // Origin : sans domaine « stateful », Sanctum n'ouvre pas de session (comme en vrai).
        return $this->withHeaders(['Origin' => 'http://localhost'])
            ->postJson('/api/auth/login', ['email' => $user->email, 'password' => $password]);
    }

    private function classPayload(string $code = 'CE1-A'): array
    {
        return [
            'cycle_id' => SchoolCycle::firstOrFail()->id,
            'code' => $code,
            'label' => 'CE1 A',
            'tuition_amount' => 100000,
        ];
    }

    private function studentPayload(int $classId): array
    {
        return [
            'class_id' => $classId,
            'first_name' => 'Awa',
            'last_name' => 'Test',
            'guardian' => ['full_name' => 'Tuteur', 'relationship_label' => 'Mère', 'phone' => '97000000'],
        ];
    }

    public function test_the_owner_creates_a_school_with_its_admin_and_a_current_year(): void
    {
        [$school, $admin, $password] = $this->createSchool('Collège Les Palmiers', [
            'subscription_due_at' => now()->addMonth()->toDateString(),
        ]);

        $this->assertSame('Admin Collège Les Palmiers', $admin->full_name);
        $this->assertSame($school->id, $admin->school_id);
        $this->assertSame('admin', $admin->role->code);
        $this->assertNotEmpty($password, 'Un mot de passe temporaire est généré et rendu une fois.');

        $this->assertTrue(AcademicYear::withoutGlobalScopes()->where('school_id', $school->id)->where('is_active', true)->exists());
        $this->assertDatabaseHas('school_settings', ['school_id' => $school->id, 'setting_key' => 'school_name', 'setting_value' => 'Collège Les Palmiers']);

        // Le nouvel administrateur peut se connecter avec le mot de passe temporaire.
        $this->loginAs($admin, $password)
            ->assertOk()
            ->assertJsonPath('user.school.name', 'Collège Les Palmiers')
            ->assertJsonPath('user.school.status', 'active');
    }

    public function test_a_school_never_sees_another_schools_data(): void
    {
        [, $adminB] = $this->createSchool('École B');
        $adminA = $this->userWithPermissions(); // école 1

        $classA = $this->actingAs($adminA)->postJson('/api/classes', $this->classPayload('SHARED'))->assertCreated()->json('id');
        $studentA = $this->actingAs($adminA)->postJson('/api/students', $this->studentPayload($classA))->assertCreated()->json('data.id');

        // L'école B ne voit rien de ce qui appartient à l'école A.
        $this->actingAs($adminB)->getJson('/api/classes')->assertOk()->assertJsonCount(0);
        $this->actingAs($adminB)->getJson('/api/students')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($adminB)->getJson('/api/debtors')->assertOk()->assertJsonCount(0);
        $this->actingAs($adminB)->getJson('/api/payments')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($adminB)->getJson('/api/dashboard/summary')->assertOk()->assertJsonPath('students', 0)->assertJsonPath('classes', 0);

        // Même en connaissant l'identifiant : introuvable, jamais « interdit ».
        $this->actingAs($adminB)->getJson("/api/students/{$studentA}")->assertNotFound();
        $this->actingAs($adminB)->putJson("/api/classes/{$classA}", ['label' => 'Piraté'])->assertNotFound();
        $this->actingAs($adminB)->getJson("/api/students/{$studentA}/balance")->assertNotFound();

        // Et l'école A retrouve bien les siennes.
        $this->actingAs($adminA)->getJson('/api/classes')->assertOk()->assertJsonCount(1);
    }

    public function test_two_schools_can_reuse_the_same_class_code_and_start_their_own_matricules(): void
    {
        [, $adminB] = $this->createSchool('École B');
        $adminA = $this->userWithPermissions();

        $classA = $this->actingAs($adminA)->postJson('/api/classes', $this->classPayload('6EME'))->assertCreated()->json('id');
        $classB = $this->actingAs($adminB)->postJson('/api/classes', $this->classPayload('6EME'))->assertCreated()->json('id');
        $this->assertNotSame($classA, $classB);

        $matriculeA = $this->actingAs($adminA)->postJson('/api/students', $this->studentPayload($classA))->assertCreated()->json('data.matricule');
        $matriculeB = $this->actingAs($adminB)->postJson('/api/students', $this->studentPayload($classB))->assertCreated()->json('data.matricule');

        $this->assertSame($matriculeA, $matriculeB, 'Chaque école numérote ses propres élèves depuis 1.');
    }

    public function test_a_school_admin_only_manages_its_own_users_and_cannot_grant_platform_rights(): void
    {
        [, $adminB] = $this->createSchool('École B');
        $adminA = $this->userWithPermissions();

        $this->actingAs($adminB)->getJson('/api/users')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($adminB)->getJson("/api/users/{$adminA->id}")->assertNotFound();
        $this->actingAs($adminB)->putJson("/api/users/{$adminA->id}", ['full_name' => 'Piraté'])->assertNotFound();
        $this->actingAs($adminB)->deleteJson("/api/users/{$adminA->id}")->assertNotFound();

        // Il crée des utilisateurs dans SON école.
        $secretary = Role::where('code', 'secretary')->value('id');
        $created = $this->actingAs($adminB)->postJson('/api/users', [
            'full_name' => 'Secrétaire B', 'email' => 'sec'.uniqid().'@ecole.test', 'password' => 'motdepasse', 'role_id' => $secretary,
        ])->assertCreated();
        $this->assertSame($adminB->school_id, $created->json('school_id'));

        // Ni le rôle propriétaire, ni un droit plateforme ne sont attribuables.
        $this->actingAs($adminB)->postJson('/api/users', [
            'full_name' => 'X', 'email' => 'x'.uniqid().'@ecole.test', 'password' => 'motdepasse',
            'role_id' => Role::where('code', 'platform_owner')->value('id'),
        ])->assertStatus(422)->assertJsonValidationErrors('role_id');

        $this->actingAs($adminB)->postJson('/api/users', [
            'full_name' => 'X', 'email' => 'y'.uniqid().'@ecole.test', 'password' => 'motdepasse',
            'role_id' => $secretary, 'permissions' => ['platform.manage'],
        ])->assertStatus(422)->assertJsonValidationErrors('permissions.0');

        // Le catalogue proposé à une école ne mentionne pas la plateforme.
        $this->assertNotContains('platform.manage', collect($this->actingAs($adminB)->getJson('/api/roles/permissions-catalog')->json())->pluck('code')->all());
        $this->assertNotContains('platform_owner', collect($this->actingAs($adminB)->getJson('/api/roles')->json())->pluck('code')->all());
    }

    public function test_the_platform_account_cannot_read_school_data_and_schools_cannot_reach_the_console(): void
    {
        $adminA = $this->userWithPermissions();
        $owner = $this->owner();

        $this->actingAs($owner)->getJson('/api/students')->assertForbidden()->assertJsonPath('code', 'platform_account');
        $this->actingAs($owner)->getJson('/api/dashboard/summary')->assertForbidden();
        $this->actingAs($owner)->getJson('/api/payments')->assertForbidden();
        $this->actingAs($owner)->getJson('/api/me')->assertOk()->assertJsonPath('school', null)->assertJsonPath('role', 'platform_owner');

        $this->actingAs($adminA)->getJson('/api/platform/schools')->assertForbidden();

        // Même un droit plateforme forcé en base ne suffit pas : il faut aussi n'avoir aucune école.
        DB::table('user_permissions')->insert([
            'user_id' => $adminA->id,
            'permission_id' => DB::table('permissions')->where('code', 'platform.manage')->value('id'),
            'granted' => 1,
        ]);
        cache()->forget("user:{$adminA->id}:permissions");
        $this->actingAs($adminA)->getJson('/api/platform/schools')->assertForbidden();
    }

    public function test_suspending_a_school_cuts_access_immediately_and_reactivating_restores_it(): void
    {
        [$school, $admin, $password] = $this->createSchool('École B');
        $owner = $this->owner();

        $this->actingAs($admin)->getJson('/api/classes')->assertOk();

        $this->actingAs($owner)->postJson("/api/platform/schools/{$school->id}/suspend", ['reason' => 'Facture de septembre impayée'])
            ->assertOk()->assertJsonPath('status', 'suspended')->assertJsonPath('suspension_kind', 'manual');

        // Une session déjà ouverte est coupée à la requête suivante.
        $this->actingAs($admin)->getJson('/api/classes')
            ->assertForbidden()
            ->assertJsonPath('code', 'school_suspended')
            ->assertJsonPath('reason', 'Facture de septembre impayée')
            ->assertJsonPath('school_name', 'École B');
        $this->actingAs($admin)->getJson('/api/me')->assertForbidden()->assertJsonPath('code', 'school_suspended');

        // Les autres écoles ne sont pas touchées.
        $this->actingAs($this->userWithPermissions())->getJson('/api/classes')->assertOk();

        $this->actingAs($owner)->postJson("/api/platform/schools/{$school->id}/reactivate")
            ->assertOk()->assertJsonPath('status', 'active');
        $this->actingAs($admin)->getJson('/api/classes')->assertOk();
    }

    public function test_a_suspended_school_cannot_open_a_session(): void
    {
        [$school, $admin, $password] = $this->createSchool('École B');

        $this->actingAs($this->owner())->postJson("/api/platform/schools/{$school->id}/suspend", ['reason' => 'Impayé'])->assertOk();

        // Le message est explicite : pas de faux « mot de passe incorrect ».
        $this->loginAs($admin, $password)
            ->assertForbidden()
            ->assertJsonPath('code', 'school_suspended')
            ->assertJsonPath('reason', 'Impayé');
    }

    public function test_an_overdue_school_keeps_working_until_automatic_suspension(): void
    {
        [$school, $admin] = $this->createSchool('École B', [
            'subscription_due_at' => now()->subDays(3)->toDateString(),
            'auto_suspend' => true,
            'grace_days' => 5,
        ]);

        // Échéance dépassée mais dans le délai de grâce : « en retard », toujours utilisable.
        $this->actingAs($admin)->getJson('/api/me')->assertOk()->assertJsonPath('school.status', 'overdue');
        $this->actingAs($admin)->getJson('/api/classes')->assertOk();

        // Délai de grâce écoulé : suspension automatique, sans intervention.
        $school->update(['subscription_due_at' => now()->subDays(10)->toDateString()]);
        $this->actingAs($admin)->getJson('/api/classes')->assertForbidden()->assertJsonPath('kind', 'payment');

        // Réactiver sans prolonger l'échéance ne servirait à rien : refusé.
        $owner = $this->owner();
        $this->actingAs($owner)->postJson("/api/platform/schools/{$school->id}/reactivate")->assertStatus(422);
        $this->actingAs($admin)->getJson('/api/classes')->assertForbidden();

        $this->actingAs($owner)->postJson("/api/platform/schools/{$school->id}/reactivate", [
            'subscription_due_at' => now()->addMonth()->toDateString(),
        ])->assertOk()->assertJsonPath('status', 'active');
        $this->actingAs($admin)->getJson('/api/classes')->assertOk();
    }

    public function test_the_console_lists_schools_without_exposing_their_data(): void
    {
        $this->createSchool('École B');
        $adminA = $this->userWithPermissions();
        $this->actingAs($adminA)->postJson('/api/classes', $this->classPayload())->assertCreated();

        $response = $this->actingAs($this->owner())->getJson('/api/platform/schools')->assertOk();

        $response->assertJsonPath('summary.total', 2)->assertJsonPath('summary.active', 2);
        $row = $response->json('schools.0');

        foreach (['students_count', 'classes_count', 'payments_count', 'total_collected'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $row, "La console ne doit pas exposer « {$forbidden} ».");
        }
    }

    public function test_the_owner_can_reset_an_admin_password(): void
    {
        [$school, $admin] = $this->createSchool('École B');

        $new = $this->actingAs($this->owner())->postJson("/api/platform/schools/{$school->id}/reset-admin-password")
            ->assertOk()->json('temporary_password');

        $this->loginAs($admin, $new)->assertOk();
    }
}
