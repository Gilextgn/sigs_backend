<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\AcademicYears\Models\AcademicYear;
use Modules\Students\Models\Guardian;
use Modules\Students\Models\Student;
use Modules\Tranches\Models\TuitionInstallment;
use Modules\Payments\Services\PaymentService;
use Tests\TestCase;

/**
 * Le backfill est une migration et non une commande artisan : l'hébergement
 * (Render, plan gratuit) n'offre pas d'accès shell, donc seules les
 * migrations lancées au démarrage peuvent réparer les données existantes.
 *
 * Sans lui, les élèves d'avant le déploiement gardent academic_year_id à
 * NULL : ils deviennent non réinscriptibles et n'apparaissent dans aucune
 * clôture d'année.
 */
class BackfillEnrollmentHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_09_15_000004_backfill_enrollment_history.php');
        $migration->up();
    }

    /** Élève tel qu'il existait avant la feature : sans année ni historique. */
    private function legacyStudent(int $classId, string $matricule, int $sequence): Student
    {
        $guardian = Guardian::create([
            'full_name' => 'Tuteur Historique',
            'relationship_label' => 'Père',
            'phone' => '97000000',
        ]);

        $student = Student::create([
            'school_id' => 1,
            'class_id' => $classId,
            'guardian_id' => $guardian->id,
            'registration_year' => (int) date('Y'),
            'registration_sequence' => $sequence,
            'matricule' => $matricule,
            'first_name' => 'Ancien',
            'last_name' => 'Eleve',
            'status' => 'active',
        ]);

        // Student::create ne met pas academic_year_id, mais on force le NULL
        // au cas où un défaut applicatif viendrait à le remplir un jour.
        DB::table('students')->where('id', $student->id)->update(['academic_year_id' => null]);

        return $student->fresh();
    }

    public function test_it_attaches_legacy_students_to_the_active_year(): void
    {
        $class = $this->createSchoolClass();
        $student = $this->legacyStudent($class->id, 'ELV-2026-000001', 1);
        $activeYear = AcademicYear::where('is_active', true)->firstOrFail();

        $this->assertNull($student->academic_year_id);
        $this->assertDatabaseCount('student_enrollments', 0);

        $this->runMigration();

        $this->assertSame($activeYear->id, $student->fresh()->academic_year_id);
        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $student->id,
            'academic_year_id' => $activeYear->id,
            'class_id' => $class->id,
        ]);
    }

    public function test_it_attaches_legacy_payments_to_the_student_year(): void
    {
        $class = $this->createSchoolClass(50000);
        $student = $this->legacyStudent($class->id, 'ELV-2026-000002', 2);
        $installment = TuitionInstallment::create([
            'class_id' => $class->id,
            'label' => 'Tranche unique',
            'amount' => 50000,
        ]);
        $cashier = $this->userWithPermissions();
        $activeYear = AcademicYear::where('is_active', true)->firstOrFail();

        // Paiement encaissé alors que l'élève n'a pas encore d'année : il
        // hérite du NULL, exactement comme les paiements d'avant la feature.
        $payment = app(PaymentService::class)->create($student->id, [[
            'item_type' => 'TRANCHE',
            'tuition_installment_id' => $installment->id,
            'paid_amount' => 50000,
        ]], $cashier->id);

        $this->assertNull($payment->fresh()->academic_year_id);

        $this->runMigration();

        $this->assertSame($activeYear->id, $payment->fresh()->academic_year_id);
    }

    public function test_it_can_be_replayed_without_duplicating_history(): void
    {
        $class = $this->createSchoolClass();
        $this->legacyStudent($class->id, 'ELV-2026-000003', 3);

        $this->runMigration();
        $this->runMigration();

        $this->assertDatabaseCount('student_enrollments', 1);
    }

    public function test_it_does_nothing_on_a_database_without_an_active_year(): void
    {
        $class = $this->createSchoolClass();
        $student = $this->legacyStudent($class->id, 'ELV-2026-000004', 4);
        AcademicYear::query()->update(['is_active' => false]);

        $this->runMigration();

        $this->assertNull($student->fresh()->academic_year_id);
        $this->assertDatabaseCount('student_enrollments', 0);
    }

    private function runCorrection(): void
    {
        $migration = require database_path('migrations/2026_09_15_000005_move_backfilled_enrollments_to_previous_year.php');
        $migration->up();
    }

    public function test_the_correction_moves_backfilled_pupils_to_the_previous_year(): void
    {
        $class = $this->createSchoolClass(50000);
        $student = $this->legacyStudent($class->id, 'ELV-2026-000005', 5);
        $activeYear = AcademicYear::where('is_active', true)->firstOrFail(); // 2026-2027

        $installment = TuitionInstallment::create(['class_id' => $class->id, 'label' => 'Tranche', 'amount' => 50000]);
        $cashier = $this->userWithPermissions();
        $payment = app(PaymentService::class)->create($student->id, [[
            'item_type' => 'TRANCHE',
            'tuition_installment_id' => $installment->id,
            'paid_amount' => 25000,
        ]], $cashier->id);

        $this->runMigration();
        $this->assertSame($activeYear->id, $student->fresh()->academic_year_id);

        $this->runCorrection();

        // 2025-2026 est créée au besoin, et l'élève y est replacé : c'est
        // pour 2026-2027 qu'il reste à le réinscrire.
        $previousYear = AcademicYear::where('code', '2025-2026')->firstOrFail();
        $this->assertSame($previousYear->id, $student->fresh()->academic_year_id);
        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $student->id,
            'academic_year_id' => $previousYear->id,
        ]);
        $this->assertSame($previousYear->id, $payment->fresh()->academic_year_id);
    }

    public function test_the_correction_leaves_enrollments_made_in_the_app_alone(): void
    {
        $class = $this->createSchoolClass();
        $student = $this->legacyStudent($class->id, 'ELV-2026-000006', 6);
        $activeYear = AcademicYear::where('is_active', true)->firstOrFail();
        $user = $this->userWithPermissions();

        // Inscription saisie dans l'application : elle porte son auteur, donc
        // la correction ne doit pas y toucher.
        DB::table('students')->where('id', $student->id)->update(['academic_year_id' => $activeYear->id]);
        DB::table('student_enrollments')->insert([
            'student_id' => $student->id,
            'academic_year_id' => $activeYear->id,
            'class_id' => $class->id,
            'enrolled_by_user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runCorrection();

        $this->assertSame($activeYear->id, $student->fresh()->academic_year_id);
        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $student->id,
            'academic_year_id' => $activeYear->id,
        ]);
    }
}
