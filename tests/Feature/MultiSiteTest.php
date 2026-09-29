<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Platform\Models\School;
use Modules\SchoolClasses\Models\SchoolClass;
use Modules\SchoolClasses\Models\SchoolCycle;
use Modules\Students\Models\Guardian;
use Modules\Students\Models\Student;
use Modules\Tranches\Models\TuitionInstallment;
use Modules\Users\Models\Role;
use Tests\TestCase;

/**
 * Groupe scolaire : le directeur (admin) passe d'un site à l'autre et voit un
 * tableau de bord commun ; le personnel de chaque site reste sur le sien.
 * Traçabilité : toute modification sensible est journalisée et notifiée.
 */
class MultiSiteTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private School $college;

    private School $primaire;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();
        $this->college = School::findOrFail(1);
        $this->college->update(['group_code' => 'LAUREATS', 'site_label' => 'Collège']);
        $this->primaire = School::create(['name' => 'Les Lauréats primaire', 'group_code' => 'LAUREATS', 'site_label' => 'Maternelle / Primaire']);
        $this->director = $this->userWithPermissions();
    }

    private function staff(School $school, array $permissions): User
    {
        $user = $this->userWithPermissions($permissions);
        $user->forceFill(['school_id' => $school->id])->save();

        return $user;
    }

    private function classIn(School $school, string $label): SchoolClass
    {
        return SchoolClass::withoutGlobalScopes()->create([
            'school_id' => $school->id, 'cycle_id' => SchoolCycle::firstOrFail()->id,
            'code' => 'C-'.uniqid(), 'label' => $label, 'tuition_amount' => 100000, 'is_active' => true,
        ]);
    }

    public function test_the_director_switches_sites_and_staff_cannot(): void
    {
        $this->classIn($this->college, '6ème');
        $this->classIn($this->primaire, 'CP');

        $this->actingAs($this->director)->getJson('/api/me')->assertOk()->assertJsonCount(2, 'sites');

        $labels = fn ($response) => collect($response->json())->pluck('label')->all();
        $this->assertSame(['6ème'], $labels($this->actingAs($this->director)->getJson('/api/classes')));
        $this->assertSame(['CP'], $labels($this->actingAs($this->director)->withHeader('X-Site-Id', (string) $this->primaire->id)->getJson('/api/classes')));

        // Ce que le directeur crée sur un site appartient à ce site.
        $id = $this->actingAs($this->director)->withHeader('X-Site-Id', (string) $this->primaire->id)
            ->postJson('/api/classes', ['cycle_id' => SchoolCycle::firstOrFail()->id, 'code' => 'CE1X', 'label' => 'CE1', 'tuition_amount' => 90000])->json('id');
        $this->assertSame($this->primaire->id, SchoolClass::withoutGlobalScopes()->find($id)->school_id);

        // Une secrétaire du collège qui demande l'autre site reste sur le sien.
        $secretary = $this->staff($this->college, ['classes.view']);
        $this->assertSame(['6ème'], $labels($this->actingAs($secretary)->withHeader('X-Site-Id', (string) $this->primaire->id)->getJson('/api/classes')));
        $this->actingAs($secretary)->getJson('/api/me')->assertJsonCount(1, 'sites');
    }

    public function test_group_dashboard_adds_up_both_sites(): void
    {
        $this->actingAs($this->director)->getJson('/api/dashboard/group')
            ->assertOk()
            ->assertJsonCount(2, 'sites')
            ->assertJsonPath('sites.1.label', 'Maternelle / Primaire')
            ->assertJsonStructure(['totals' => ['students', 'today', 'month', 'outstanding', 'cancellations_today', 'pending_amount'], 'sites' => [['cash' => ['cashiers_with_pending', 'pending_amount']]]]);

        $this->actingAs($this->staff($this->college, ['dashboard.view']))->getJson('/api/dashboard/group')->assertForbidden();
    }

    public function test_a_cashier_on_the_other_site_notifies_the_director_with_the_site_name(): void
    {
        $class = $this->classIn($this->primaire, 'CP');
        $installment = TuitionInstallment::withoutGlobalScopes()->create(['school_id' => $this->primaire->id, 'class_id' => $class->id, 'label' => '1ère tranche', 'amount' => 50000]);
        $guardian = Guardian::withoutGlobalScopes()->create(['school_id' => $this->primaire->id, 'full_name' => 'P', 'relationship_label' => 'Père', 'phone' => '0166000000']);
        $student = Student::withoutGlobalScopes()->create([
            'school_id' => $this->primaire->id, 'class_id' => $class->id, 'guardian_id' => $guardian->id, 'registration_year' => 2026,
            'registration_sequence' => 1, 'matricule' => 'P-1', 'first_name' => 'Koffi', 'last_name' => 'AGBO', 'status' => 'active',
        ]);
        $cashier = $this->staff($this->primaire, ['payments.create', 'payments.view']);

        $this->actingAs($cashier)->postJson('/api/payments', [
            'student_id' => $student->id, 'items' => [['item_type' => 'TRANCHE', 'tuition_installment_id' => $installment->id, 'paid_amount' => 20000]],
        ])->assertCreated();

        $this->actingAs($this->director)->getJson('/api/notifications')
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.site', 'Maternelle / Primaire');
    }

    public function test_sensitive_changes_are_traced_with_before_and_after(): void
    {
        $class = $this->createSchoolClass(150000);
        $installment = TuitionInstallment::create(['class_id' => $class->id, 'label' => '2ème tranche', 'amount' => 50000]);
        $accountant = $this->staff($this->college, ['tranches.view', 'tranches.manage']);

        $this->actingAs($accountant)->putJson("/api/tranches/{$installment->id}", ['class_id' => $class->id, 'label' => '2ème tranche', 'amount' => 30000])->assertOk();

        $log = \Modules\Security\Models\AuditLog::where('action_code', 'tuitioninstallment.updated')->firstOrFail();
        $this->assertSame($accountant->id, $log->actor_user_id);
        $this->assertEquals(50000, $log->changes_json['before']['amount']);
        $this->assertEquals(30000, $log->changes_json['after']['amount']);

        // Changement de tarif par un non-administrateur : le directeur est prévenu.
        $this->actingAs($this->director)->getJson('/api/notifications')
            ->assertJsonPath('data.0.title', 'Tranche modifié(e) par '.$accountant->full_name);

        // Les champs chiffrés (nom de l'élève) ne sont jamais recopiés en clair.
        $guardian = Guardian::create(['full_name' => 'P', 'relationship_label' => 'Père', 'phone' => '0166000000']);
        $this->actingAs($this->director)->postJson('/api/students', [
            'class_id' => $class->id, 'first_name' => 'Secret', 'last_name' => 'NOM', 'gender' => 'F', 'guardian_id' => $guardian->id,
        ]);
        $studentLog = \Modules\Security\Models\AuditLog::where('action_code', 'student.created')->first();
        if ($studentLog) {
            $this->assertStringNotContainsString('Secret', json_encode($studentLog->changes_json));
        }
    }

    public function test_logins_are_traced(): void
    {
        $user = User::create(['full_name' => 'Secrétaire', 'email' => 'sec@test.bj', 'password' => 'motdepasse', 'role_id' => Role::where('code', 'admin')->value('id'), 'status' => 'active', 'school_id' => 1]);
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        // Origin : sans domaine « stateful », Sanctum n'ouvre pas de session (comme en vrai).
        $this->withHeaders(['Origin' => 'http://localhost'])->postJson('/api/auth/login', ['email' => 'sec@test.bj', 'password' => 'motdepasse'])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action_code' => 'auth.login', 'actor_user_id' => $user->id, 'school_id' => 1]);
    }
}
