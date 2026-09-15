<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AcademicYears\Models\AcademicYear;
use Modules\SchoolClasses\Models\SchoolClass;
use Modules\Students\Models\Guardian;
use Modules\Students\Models\Student;
use Modules\Students\Models\StudentEnrollment;
use Modules\Tranches\Models\TuitionInstallment;
use Tests\TestCase;

/**
 * Réinscrire, c'est rattacher un élève de l'année écoulée à l'année que
 * l'école a déclarée active — celle pour laquelle elle inscrit en ce moment
 * (une école réinscrit en septembre, pour l'année qui commence).
 *
 * Le blocage est strict tant qu'une année clôturée reste impayée.
 */
class ReEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /** Année écoulée : celle d'où viennent les élèves à réinscrire. */
    private function previousYear(): AcademicYear
    {
        return AcademicYear::create(['code' => '2025-2026', 'label' => 'Annee 2025-2026', 'is_active' => false]);
    }

    private function aStudent(SchoolClass $class, AcademicYear $year, int $sequence = 1): Student
    {
        $guardian = Guardian::create([
            'full_name' => 'Tuteur Test',
            'relationship_label' => 'Père',
            'phone' => '97000000',
        ]);

        $student = Student::create([
            'school_id' => 1,
            'class_id' => $class->id,
            'academic_year_id' => $year->id,
            'guardian_id' => $guardian->id,
            'registration_year' => (int) date('Y'),
            'registration_sequence' => $sequence,
            'matricule' => 'ELV-'.date('Y').'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'first_name' => 'Élève',
            'last_name' => 'Test',
            'status' => 'active',
        ]);

        StudentEnrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
        ]);

        return $student;
    }

    public function test_it_re_enrolls_last_year_student_into_the_active_year(): void
    {
        $user = $this->userWithPermissions();
        $activeYear = AcademicYear::where('is_active', true)->firstOrFail(); // 2026-2027
        $lastYear = $this->previousYear();
        $oldClass = $this->createSchoolClass();
        $student = $this->aStudent($oldClass, $lastYear);
        $newClass = $this->createSchoolClass();

        $this->actingAs($user)
            ->postJson("/api/students/{$student->id}/re-enroll", ['class_id' => $newClass->id])
            ->assertOk()
            ->assertJsonPath('data.class.id', $newClass->id)
            ->assertJsonPath('data.academic_year.code', $activeYear->code);

        // L'historique garde l'année écoulée, la fiche pointe sur l'année active.
        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $student->id,
            'academic_year_id' => $lastYear->id,
            'class_id' => $oldClass->id,
        ]);
        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $student->id,
            'academic_year_id' => $activeYear->id,
            'class_id' => $newClass->id,
        ]);
        $this->assertSame($activeYear->id, $student->fresh()->academic_year_id);
    }

    public function test_it_refuses_a_student_already_enrolled_for_the_active_year(): void
    {
        $user = $this->userWithPermissions();
        $activeYear = AcademicYear::where('is_active', true)->firstOrFail();
        $class = $this->createSchoolClass();
        $student = $this->aStudent($class, $activeYear);

        $this->actingAs($user)
            ->postJson("/api/students/{$student->id}/re-enroll", ['class_id' => $class->id])
            ->assertStatus(422);

        $this->assertDatabaseCount('student_enrollments', 1);
    }

    public function test_the_context_announces_the_active_year_as_target(): void
    {
        $user = $this->userWithPermissions();
        $activeYear = AcademicYear::where('is_active', true)->firstOrFail();
        $lastYear = $this->previousYear();
        $class = $this->createSchoolClass();
        $student = $this->aStudent($class, $lastYear);

        $this->actingAs($user)
            ->getJson("/api/students/{$student->id}/re-enrollment-context")
            ->assertOk()
            ->assertJsonPath('last_year.code', $lastYear->code)
            ->assertJsonPath('target_year.code', $activeYear->code)
            ->assertJsonPath('blocked_reason', null);
    }

    public function test_it_refuses_when_no_year_is_active(): void
    {
        $user = $this->userWithPermissions();
        $lastYear = $this->previousYear();
        $class = $this->createSchoolClass();
        $student = $this->aStudent($class, $lastYear);
        AcademicYear::query()->update(['is_active' => false]);

        $this->actingAs($user)
            ->getJson("/api/students/{$student->id}/re-enrollment-context")
            ->assertOk()
            ->assertJsonPath('target_year', null)
            ->assertJsonPath('blocked_reason', "Aucune année scolaire active : activez l'année en cours dans Paramètres.");

        $this->actingAs($user)
            ->postJson("/api/students/{$student->id}/re-enroll", ['class_id' => $class->id])
            ->assertStatus(422);
    }

    public function test_the_block_is_strict_even_for_a_full_permission_user(): void
    {
        $admin = $this->userWithPermissions(); // toutes les permissions
        $lastYear = $this->previousYear();
        $class = $this->createSchoolClass(300000);
        $student = $this->aStudent($class, $lastYear);

        $this->actingAs($admin)->postJson("/api/academic-years/{$lastYear->id}/close")->assertOk();

        $this->actingAs($admin)
            ->postJson("/api/students/{$student->id}/re-enroll", ['class_id' => $class->id])
            ->assertStatus(422)
            ->assertJsonPath('debts.0.academic_year', $lastYear->code)
            ->assertJsonPath('debts.0.outstanding_amount', 300000);

        // Aucune échappatoire : même en renvoyant un ancien paramètre override.
        $this->actingAs($admin)
            ->postJson("/api/students/{$student->id}/re-enroll", ['class_id' => $class->id, 'override' => true])
            ->assertStatus(422);

        $this->assertDatabaseCount('student_enrollments', 1);
    }

    public function test_paying_the_old_debt_unblocks_the_re_enrollment(): void
    {
        $user = $this->userWithPermissions();
        $lastYear = $this->previousYear();
        $class = $this->createSchoolClass(50000);
        $student = $this->aStudent($class, $lastYear);
        $installment = TuitionInstallment::create([
            'class_id' => $class->id,
            'label' => 'Scolarité complète',
            'amount' => 50000,
        ]);

        $this->actingAs($user)->postJson("/api/academic-years/{$lastYear->id}/close")->assertOk();

        $this->actingAs($user)
            ->postJson("/api/students/{$student->id}/re-enroll", ['class_id' => $class->id])
            ->assertStatus(422);

        // Encaissé après la clôture : le paiement suit l'année de l'élève
        // (pas encore réinscrit), donc il solde bien cette dette-là.
        $this->actingAs($user)->postJson('/api/payments', [
            'student_id' => $student->id,
            'items' => [['item_type' => 'TRANCHE', 'tuition_installment_id' => $installment->id, 'paid_amount' => 50000]],
        ])->assertCreated();

        $this->actingAs($user)
            ->getJson("/api/academic-years/{$lastYear->id}/closing-preview")
            ->assertOk()
            ->assertJsonCount(0);

        $this->actingAs($user)
            ->postJson("/api/students/{$student->id}/re-enroll", ['class_id' => $class->id])
            ->assertOk();
    }

    public function test_closing_and_reopening_a_year_flips_its_state(): void
    {
        $user = $this->userWithPermissions();
        $year = AcademicYear::where('is_active', true)->firstOrFail();

        $this->actingAs($user)->postJson("/api/academic-years/{$year->id}/close")->assertOk();
        $this->assertNotNull($year->fresh()->closed_at);

        // Une année déjà clôturée ne peut pas l'être deux fois.
        $this->actingAs($user)->postJson("/api/academic-years/{$year->id}/close")->assertStatus(422);

        $this->actingAs($user)->postJson("/api/academic-years/{$year->id}/reopen")->assertOk();
        $this->assertNull($year->fresh()->closed_at);
    }
}
