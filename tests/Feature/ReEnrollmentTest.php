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
 * Réinscription d'un élève existant pour l'année SUIVANTE (et non l'année
 * active : une école réinscrit couramment en juin), et blocage strict tant
 * qu'une année clôturée reste impayée.
 */
class ReEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function aStudent(SchoolClass $class, AcademicYear $year): Student
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
            'registration_sequence' => 1,
            'matricule' => 'ELV-'.date('Y').'-000001',
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

    private function nextYear(): AcademicYear
    {
        return AcademicYear::create(['code' => '2027-2028', 'label' => 'Année 2027-2028', 'is_active' => false]);
    }

    public function test_it_re_enrolls_into_the_following_year_without_activating_it(): void
    {
        $user = $this->userWithPermissions();
        $currentYear = AcademicYear::where('is_active', true)->firstOrFail();
        $oldClass = $this->createSchoolClass();
        $student = $this->aStudent($oldClass, $currentYear);

        // L'année suivante existe mais n'est pas activée : la réinscription
        // doit quand même la viser (cas « on réinscrit en juin »).
        $newYear = $this->nextYear();
        $newClass = $this->createSchoolClass();

        $this->actingAs($user)
            ->postJson("/api/students/{$student->id}/re-enroll", ['class_id' => $newClass->id])
            ->assertOk()
            ->assertJsonPath('data.class.id', $newClass->id);

        $this->assertTrue($currentYear->fresh()->is_active, "L'année en cours doit rester active.");

        // L'historique conserve l'ancienne année, la fiche élève pointe sur la nouvelle.
        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $student->id,
            'academic_year_id' => $currentYear->id,
            'class_id' => $oldClass->id,
        ]);
        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $student->id,
            'academic_year_id' => $newYear->id,
            'class_id' => $newClass->id,
        ]);
        $this->assertSame($newClass->id, $student->fresh()->class_id);
        $this->assertSame($newYear->id, $student->fresh()->academic_year_id);
    }

    public function test_it_refuses_when_no_following_year_exists(): void
    {
        $user = $this->userWithPermissions();
        $currentYear = AcademicYear::where('is_active', true)->firstOrFail();
        $class = $this->createSchoolClass();
        $student = $this->aStudent($class, $currentYear);

        $this->actingAs($user)
            ->postJson("/api/students/{$student->id}/re-enroll", ['class_id' => $class->id])
            ->assertStatus(422);

        $this->assertDatabaseCount('student_enrollments', 1);
    }

    public function test_it_refuses_a_second_enrollment_for_the_same_year(): void
    {
        $user = $this->userWithPermissions();
        $currentYear = AcademicYear::where('is_active', true)->firstOrFail();
        $class = $this->createSchoolClass();
        $student = $this->aStudent($class, $currentYear);
        $this->nextYear();

        $this->actingAs($user)
            ->postJson("/api/students/{$student->id}/re-enroll", ['class_id' => $class->id])
            ->assertOk();

        // Le second appel vise désormais une année encore inexistante (2028-2029).
        $this->actingAs($user)
            ->postJson("/api/students/{$student->id}/re-enroll", ['class_id' => $class->id])
            ->assertStatus(422);
    }

    public function test_the_block_is_strict_even_for_a_full_permission_user(): void
    {
        $admin = $this->userWithPermissions(); // toutes les permissions
        $currentYear = AcademicYear::where('is_active', true)->firstOrFail();
        $class = $this->createSchoolClass(300000);
        $student = $this->aStudent($class, $currentYear);
        $this->nextYear();

        $this->actingAs($admin)->postJson("/api/academic-years/{$currentYear->id}/close")->assertOk();

        $this->actingAs($admin)
            ->postJson("/api/students/{$student->id}/re-enroll", ['class_id' => $class->id])
            ->assertStatus(422)
            ->assertJsonPath('debts.0.academic_year', $currentYear->code)
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
        $currentYear = AcademicYear::where('is_active', true)->firstOrFail();
        $class = $this->createSchoolClass(50000);
        $student = $this->aStudent($class, $currentYear);
        $installment = TuitionInstallment::create([
            'class_id' => $class->id,
            'label' => 'Scolarité complète',
            'amount' => 50000,
        ]);
        $this->nextYear();

        $this->actingAs($user)->postJson("/api/academic-years/{$currentYear->id}/close")->assertOk();

        $this->actingAs($user)
            ->postJson("/api/students/{$student->id}/re-enroll", ['class_id' => $class->id])
            ->assertStatus(422);

        // Le paiement est enregistré après la clôture : il reste rattaché à
        // l'année de l'élève (non encore réinscrit), donc il solde cette dette.
        $this->actingAs($user)->postJson('/api/payments', [
            'student_id' => $student->id,
            'items' => [['item_type' => 'TRANCHE', 'tuition_installment_id' => $installment->id, 'paid_amount' => 50000]],
        ])->assertCreated();

        $this->actingAs($user)
            ->getJson("/api/academic-years/{$currentYear->id}/closing-preview")
            ->assertOk()
            ->assertJsonCount(0);

        $this->actingAs($user)
            ->postJson("/api/students/{$student->id}/re-enroll", ['class_id' => $class->id])
            ->assertOk();
    }

    public function test_the_context_endpoint_announces_the_target_year_and_the_block(): void
    {
        $user = $this->userWithPermissions();
        $currentYear = AcademicYear::where('is_active', true)->firstOrFail();
        $class = $this->createSchoolClass(300000);
        $student = $this->aStudent($class, $currentYear);
        $newYear = $this->nextYear();

        $this->actingAs($user)
            ->getJson("/api/students/{$student->id}/re-enrollment-context")
            ->assertOk()
            ->assertJsonPath('target_year.code', $newYear->code)
            ->assertJsonPath('blocked_reason', null);

        $this->actingAs($user)->postJson("/api/academic-years/{$currentYear->id}/close")->assertOk();

        $this->actingAs($user)
            ->getJson("/api/students/{$student->id}/re-enrollment-context")
            ->assertOk()
            ->assertJsonPath('debts.0.outstanding_amount', 300000)
            ->assertJsonPath('blocked_reason', "Réinscription bloquée : le solde d'une année clôturée n'est pas réglé.");
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
