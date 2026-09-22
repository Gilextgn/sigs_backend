<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Platform\Models\School;
use Modules\Users\Models\Role;
use Tests\TestCase;

/**
 * Le compte du propriétaire (profil, mot de passe, démarrage) et les mots de
 * passe temporaires qu'il remet aux administrateurs d'école.
 */
class PlatformAccountTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function owner(array $attributes = []): User
    {
        return User::create([
            'school_id' => null,
            'role_id' => Role::where('code', 'platform_owner')->value('id'),
            'full_name' => 'Propriétaire',
            'email' => 'owner@sigs.test',
            'password' => Hash::make('ancien-mdp'),
            'status' => 'active',
            ...$attributes,
        ]);
    }

    /** @return array{0: School, 1: User, 2: string} */
    private function createSchool(User $owner): array
    {
        $response = $this->actingAs($owner)->postJson('/api/platform/schools', [
            'name' => 'École B',
            'admin_name' => 'Directeur B',
            'admin_email' => 'directeur@ecole-b.test',
        ])->assertCreated();

        return [School::findOrFail($response->json('id')), User::where('email', 'directeur@ecole-b.test')->firstOrFail(), $response->json('temporary_password')];
    }

    public function test_the_owner_changes_their_password_with_the_current_one(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->putJson('/api/auth/password', [
            'current_password' => 'mauvais',
            'password' => 'nouveau-mdp-123',
            'password_confirmation' => 'nouveau-mdp-123',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->actingAs($owner)->putJson('/api/auth/password', [
            'current_password' => 'ancien-mdp',
            'password' => 'nouveau-mdp-123',
            'password_confirmation' => 'autre-chose',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->actingAs($owner)->putJson('/api/auth/password', [
            'current_password' => 'ancien-mdp',
            'password' => 'nouveau-mdp-123',
            'password_confirmation' => 'nouveau-mdp-123',
        ])->assertOk();

        $owner->refresh();
        $this->assertTrue(Hash::check('nouveau-mdp-123', $owner->password));
        $this->assertNotNull($owner->password_changed_at);
    }

    public function test_the_owner_updates_their_profile_and_needs_the_password_to_change_the_email(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->putJson('/api/platform/account', ['full_name' => 'Gil T.', 'email' => 'owner@sigs.test'])
            ->assertOk()->assertJsonPath('user.full_name', 'Gil T.');

        $this->actingAs($owner)->putJson('/api/platform/account', ['full_name' => 'Gil T.', 'email' => 'gil@sigs.test'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->actingAs($owner)->putJson('/api/platform/account', ['full_name' => 'Gil T.', 'email' => 'gil@sigs.test', 'current_password' => 'ancien-mdp'])
            ->assertOk()->assertJsonPath('user.email', 'gil@sigs.test');
    }

    public function test_a_school_admin_cannot_use_the_owner_account_endpoint(): void
    {
        $this->actingAs($this->userWithPermissions())->putJson('/api/platform/account', ['full_name' => 'X', 'email' => 'x@sigs.test'])
            ->assertForbidden();
    }

    public function test_a_temporary_password_must_be_replaced_before_working(): void
    {
        [, $admin, $temporary] = $this->createSchool($this->owner());

        $this->assertTrue($admin->must_change_password);

        // Seul /api/me répond, pour que l'écran de changement s'affiche.
        $this->actingAs($admin)->getJson('/api/me')->assertOk()->assertJsonPath('must_change_password', true);
        $this->actingAs($admin)->getJson('/api/classes')->assertForbidden()->assertJsonPath('code', 'password_change_required');

        $this->actingAs($admin)->putJson('/api/auth/password', [
            'current_password' => $temporary,
            'password' => 'mon-propre-mdp',
            'password_confirmation' => 'mon-propre-mdp',
        ])->assertOk()->assertJsonPath('user.must_change_password', false);

        $this->actingAs($admin->fresh())->getJson('/api/classes')->assertOk();
    }

    public function test_a_reset_password_is_temporary_too(): void
    {
        $owner = $this->owner();
        [$school, $admin] = $this->createSchool($owner);
        $admin->forceFill(['must_change_password' => false])->save();

        $this->actingAs($owner)->postJson("/api/platform/schools/{$school->id}/reset-admin-password")->assertOk();

        $this->assertTrue($admin->fresh()->must_change_password);
    }

    public function test_the_owner_renames_a_school_and_corrects_its_admin(): void
    {
        $owner = $this->owner();
        [$school, $admin] = $this->createSchool($owner);

        $this->actingAs($owner)->putJson("/api/platform/schools/{$school->id}", ['name' => 'Complexe B'])
            ->assertOk()->assertJsonPath('name', 'Complexe B')->assertJsonPath('events.0.action', 'renamed');

        // Le nom imprimé par l'école sur ses reçus reste le sien.
        $this->assertDatabaseHas('school_settings', ['school_id' => $school->id, 'setting_key' => 'school_name', 'setting_value' => 'École B']);

        $this->actingAs($owner)->putJson("/api/platform/schools/{$school->id}/admins/{$admin->id}", ['full_name' => 'Nouveau directeur', 'email' => 'nouveau@ecole-b.test'])
            ->assertOk()->assertJsonPath('admins.0.email', 'nouveau@ecole-b.test')->assertJsonPath('events.0.action', 'admin_updated');

        // E-mail déjà pris par un autre compte : refusé.
        $this->actingAs($owner)->putJson("/api/platform/schools/{$school->id}/admins/{$admin->id}", ['email' => 'owner@sigs.test'])
            ->assertUnprocessable();

        // Un compte d'une autre école n'est pas modifiable par ce biais.
        $other = $this->userWithPermissions();
        $this->actingAs($owner)->putJson("/api/platform/schools/{$school->id}/admins/{$other->id}", ['full_name' => 'Piraté'])
            ->assertNotFound();
    }

    public function test_a_restart_keeps_the_password_chosen_in_the_console(): void
    {
        $owner = $this->owner(['password_changed_at' => now()]);

        $this->artisan('platform:create-owner', ['email' => 'owner@sigs.test', 'password' => 'mdp-environnement'])->assertSuccessful();
        $this->assertTrue(Hash::check('ancien-mdp', $owner->fresh()->password));

        // Accès perdu : on réimpose explicitement celui de l'environnement.
        $this->artisan('platform:create-owner', ['email' => 'owner@sigs.test', 'password' => 'mdp-environnement', '--reset-password' => true])->assertSuccessful();
        $this->assertTrue(Hash::check('mdp-environnement', $owner->fresh()->password));
    }

    public function test_a_restart_does_not_create_a_second_owner_after_an_email_change(): void
    {
        $owner = $this->owner(['email' => 'nouvel-email@sigs.test']);

        $this->artisan('platform:create-owner', ['email' => 'owner@sigs.test', 'password' => 'mdp-environnement'])->assertSuccessful();

        $this->assertSame(1, User::whereNull('school_id')->count());
        $this->assertSame('nouvel-email@sigs.test', $owner->fresh()->email);
    }
}
