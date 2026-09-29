<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Platform\Models\School;
use Modules\Teachers\Models\Subject;
use Modules\Teachers\Models\TeachingSession;
use Modules\Users\Models\Role;
use Tests\TestCase;

/**
 * Qui voit quoi : le directeur règle les droits de chaque compte (y compris
 * retirer ce que le rôle donne), la plateforme masque des onglets pour une
 * école, et le primaire saisit la présence du maître à la journée.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_the_director_can_remove_a_permission_the_role_gives(): void
    {
        $director = $this->userWithPermissions();
        $secretaryRole = Role::where('code', 'secretary')->firstOrFail();
        $roleCodes = $secretaryRole->permissions()->pluck('code')->all();
        $this->assertContains('students.view', $roleCodes);
        $this->assertNotContains('teachers.view', $roleCodes); // retiré du rôle par défaut

        $id = $this->actingAs($director)->postJson('/api/users', [
            'full_name' => 'Secrétaire', 'email' => 'sec@ecole.test', 'password' => 'motdepasse123', 'password_confirmation' => 'motdepasse123',
            'role_id' => $secretaryRole->id, 'status' => 'active',
            // Tout le rôle sauf la réinscription, plus la consultation des enseignants.
            'permissions' => [...array_diff($roleCodes, ['students.reenroll']), 'teachers.view'],
            'permissions_mode' => 'effective',
        ])->assertCreated()->json('id');

        $permissions = User::find($id)->permissions();
        $this->assertNotContains('students.reenroll', $permissions);
        $this->assertContains('teachers.view', $permissions);
        $this->assertContains('students.view', $permissions);

        $this->actingAs($director)->getJson("/api/users/{$id}")->assertOk()->assertJsonPath('effective_permissions', array_values($permissions));
    }

    public function test_the_platform_hides_menu_tabs_for_a_school(): void
    {
        School::findOrFail(1)->update(['hidden_modules' => ['payroll', 'rentree']]);
        $this->actingAs($this->userWithPermissions())->getJson('/api/me')->assertOk()->assertJsonPath('hidden_modules', ['payroll', 'rentree']);
    }

    public function test_a_primary_teacher_attendance_is_recorded_for_the_whole_day(): void
    {
        $admin = $this->userWithPermissions();
        $teacherId = $this->actingAs($admin)->postJson('/api/teachers', ['full_name' => 'Maître CE1', 'level' => 'primary', 'monthly_salary' => 80000])->json('id');
        $classId = $this->createSchoolClass()->id;
        $assignments = $this->actingAs($admin)->postJson('/api/teacher-assignments', ['teacher_id' => $teacherId, 'class_id' => $classId, 'subject_ids' => Subject::limit(2)->pluck('id')->all()])->json();

        foreach ($assignments as $index => $assignment) {
            TeachingSession::create([
                'school_id' => 1, 'class_id' => $classId, 'subject_id' => $assignment['subject_id'], 'teacher_assignment_id' => $assignment['id'],
                'session_date' => '2026-10-07', 'starts_at' => $index ? '10:00' : '08:00', 'ends_at' => $index ? '12:00' : '10:00', 'planned_minutes' => 120,
            ]);
        }

        $this->actingAs($admin)->postJson('/api/teacher-attendances/day', ['teacher_id' => $teacherId, 'class_id' => $classId, 'date' => '2026-10-07', 'status' => 'late', 'absence_minutes' => 15, 'reason' => 'Pluie'])
            ->assertOk()->assertJsonCount(2);

        $this->assertDatabaseCount('teacher_attendances', 2);
        $this->assertDatabaseHas('teacher_attendances', ['status' => 'late', 'absence_minutes' => 15]);
        $this->assertDatabaseHas('teacher_attendances', ['status' => 'late', 'absence_minutes' => 0]);
    }
}
