<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AcademicYears\Models\AcademicYear;
use Modules\SchoolClasses\Models\SchoolClass;
use Modules\Students\Models\Guardian;
use Modules\Students\Models\Student;
use Modules\Tranches\Models\TuitionInstallment;
use Tests\TestCase;

/**
 * Ce que lisent l'accueil, la barre du haut et la fiche élève.
 */
class HomeScreensTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function aStudent(SchoolClass $class): Student
    {
        $guardian = Guardian::create(['full_name' => 'Tuteur', 'relationship_label' => 'Mère', 'phone' => '97000001']);

        return Student::create([
            'school_id' => 1,
            'class_id' => $class->id,
            'guardian_id' => $guardian->id,
            'registration_year' => (int) date('Y'),
            'registration_sequence' => 1,
            'matricule' => 'ELV-'.date('Y').'-000001',
            'first_name' => 'Awa',
            'last_name' => 'Test',
            'status' => 'active',
        ]);
    }

    public function test_the_student_balance_lists_partial_lines(): void
    {
        $user = $this->userWithPermissions();
        $class = $this->createSchoolClass(200000);
        $student = $this->aStudent($class);
        $first = TuitionInstallment::create(['class_id' => $class->id, 'label' => '1ère tranche', 'amount' => 100000]);
        TuitionInstallment::create(['class_id' => $class->id, 'label' => '2ème tranche', 'amount' => 100000]);

        $this->actingAs($user)->postJson('/api/payments', [
            'student_id' => $student->id,
            'items' => [['item_type' => 'TRANCHE', 'tuition_installment_id' => $first->id, 'paid_amount' => 30000]],
        ])->assertCreated();

        $this->actingAs($user)->getJson("/api/students/{$student->id}/balance")
            ->assertOk()
            ->assertJsonPath('outstanding_amount', 170000)
            ->assertJsonPath('unpaid_items.0.label', '1ère tranche')
            ->assertJsonPath('unpaid_items.0.status', 'partial')
            ->assertJsonPath('unpaid_items.0.remaining', 70000)
            ->assertJsonPath('unpaid_items.1.status', 'unpaid');
    }

    public function test_the_summary_reports_this_month_and_today(): void
    {
        $user = $this->userWithPermissions();
        $class = $this->createSchoolClass(100000);
        $student = $this->aStudent($class);
        $installment = TuitionInstallment::create(['class_id' => $class->id, 'label' => 'Scolarité', 'amount' => 100000]);

        $this->actingAs($user)->postJson('/api/payments', [
            'student_id' => $student->id,
            'items' => [['item_type' => 'TRANCHE', 'tuition_installment_id' => $installment->id, 'paid_amount' => 25000]],
        ])->assertCreated();

        $summary = $this->actingAs($user)->getJson('/api/dashboard/summary')->assertOk();

        $this->assertEqualsWithDelta(25000, $summary->json('month.total'), 0.001);
        $this->assertSame(1, $summary->json('month.payment_count'));
        $this->assertEqualsWithDelta(25000, $summary->json('today.total'), 0.001);
        $this->assertNull($summary->json('month.change_percent'), 'Sans mois précédent, pas de variation inventée.');

        $this->actingAs($user)->getJson('/api/dashboard/recent-payments')
            ->assertOk()
            ->assertJsonPath('0.cashier.full_name', $user->full_name);
    }

    public function test_a_cashier_reads_the_active_year_and_the_letterhead(): void
    {
        $cashier = $this->userWithPermissions(['payments.create', 'payments.view']);
        $year = AcademicYear::where('is_active', true)->firstOrFail();

        $this->actingAs($cashier)->getJson('/api/academic-years/active')
            ->assertOk()
            ->assertJsonPath('code', $year->code);

        // Ses reçus doivent porter l'en-tête de l'école.
        $this->actingAs($cashier)->getJson('/api/settings')->assertOk();
        $this->actingAs($cashier)->putJson('/api/settings', ['school_name' => 'X'])->assertForbidden();
    }
}
