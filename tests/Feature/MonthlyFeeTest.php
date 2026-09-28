<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\AcademicYears\Models\AcademicYear;
use Modules\Students\Models\Guardian;
use Modules\Students\Models\Student;
use Tests\TestCase;

class MonthlyFeeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $admin;

    private Student $student;

    private int $classId;

    protected function setUp(): void
    {
        parent::setUp();

        // Année 2026-2027 (seedée, début 1er septembre) : septembre, octobre, novembre sont échus.
        Carbon::setTestNow('2026-11-15 10:00:00');
        $this->admin = $this->userWithPermissions();
        $this->classId = $this->createSchoolClass(100000)->id;
        $guardian = Guardian::create(['full_name' => 'Parent', 'relationship_label' => 'Mère', 'phone' => '66000002']);
        $this->student = Student::create([
            'school_id' => 1, 'class_id' => $this->classId, 'guardian_id' => $guardian->id,
            'academic_year_id' => AcademicYear::where('is_active', true)->value('id'),
            'registration_year' => 2026, 'registration_sequence' => 1,
            'matricule' => 'ELV-2026-000001', 'first_name' => 'Sena', 'last_name' => 'Houngbo', 'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createFee(array $data): int
    {
        return $this->actingAs($this->admin)->postJson('/api/fees', [
            'label' => 'Cantine', 'amount' => 5000, 'billing_cycle' => 'monthly', 'class_ids' => [$this->classId], ...$data,
        ])->assertSuccessful()->json('id');
    }

    private function pay(array $items)
    {
        return $this->actingAs($this->admin)->postJson('/api/payments', ['student_id' => $this->student->id, 'items' => $items]);
    }

    private function unpaidLabels(): array
    {
        return collect($this->actingAs($this->admin)->getJson("/api/students/{$this->student->id}/balance")->assertOk()->json('unpaid_items'))
            ->pluck('label')->all();
    }

    public function test_a_monthly_fee_is_owed_month_by_month_and_payable_in_advance(): void
    {
        $feeId = $this->createFee(['is_mandatory' => true]);

        // Dix mois facturés (septembre → juin), trois échus.
        $lines = $this->actingAs($this->admin)->getJson("/api/students/{$this->student->id}/payable-lines")->assertOk()->json('lines');
        $this->assertCount(10, collect($lines)->where('group', 'Frais mensuels'));
        $this->assertSame(['Cantine — septembre', 'Cantine — octobre', 'Cantine — novembre'], $this->unpaidLabels());

        // Octobre + décembre (d'avance) en un seul paiement.
        $payment = $this->pay([
            ['item_type' => 'AUTRE_FRAIS', 'fee_type_id' => $feeId, 'period_month' => 10, 'paid_amount' => 5000],
            ['item_type' => 'AUTRE_FRAIS', 'fee_type_id' => $feeId, 'period_month' => 12, 'paid_amount' => 5000],
        ])->assertCreated();
        $this->assertSame(['Cantine — octobre', 'Cantine — décembre'], collect($payment->json('items'))->pluck('label')->all());
        $this->assertSame(['Cantine — septembre', 'Cantine — novembre'], $this->unpaidLabels());

        // Acompte sur novembre accepté, dépassement refusé.
        $this->pay([['item_type' => 'AUTRE_FRAIS', 'fee_type_id' => $feeId, 'period_month' => 11, 'paid_amount' => 2000]])->assertCreated();
        $this->pay([['item_type' => 'AUTRE_FRAIS', 'fee_type_id' => $feeId, 'period_month' => 11, 'paid_amount' => 4000]])->assertStatus(422);

        // Mois obligatoire et facturé.
        $this->pay([['item_type' => 'AUTRE_FRAIS', 'fee_type_id' => $feeId, 'paid_amount' => 1000]])->assertStatus(422);
        $this->pay([['item_type' => 'AUTRE_FRAIS', 'fee_type_id' => $feeId, 'period_month' => 7, 'paid_amount' => 1000]])->assertStatus(422);
    }

    public function test_an_optional_monthly_fee_is_only_owed_by_subscribed_students(): void
    {
        $feeId = $this->createFee(['label' => 'TD', 'is_mandatory' => false, 'months' => [10, 11, 12]]);
        $this->assertSame([], $this->unpaidLabels());

        $this->actingAs($this->admin)->getJson("/api/students/{$this->student->id}/fee-subscriptions")
            ->assertOk()->assertJsonPath('0.label', 'TD')->assertJsonPath('0.subscribed', false);

        $this->actingAs($this->admin)->putJson("/api/students/{$this->student->id}/fee-subscriptions", ['fee_type_ids' => [$feeId]])
            ->assertOk()->assertJsonPath('0.subscribed', true);
        $this->assertSame(['TD — octobre', 'TD — novembre'], $this->unpaidLabels());

        $this->actingAs($this->admin)->putJson("/api/students/{$this->student->id}/fee-subscriptions", ['fee_type_ids' => []])->assertOk();
        $this->assertSame([], $this->unpaidLabels());
    }

    public function test_a_one_time_fee_keeps_working_as_before(): void
    {
        $feeId = $this->createFee(['label' => 'Tenue', 'billing_cycle' => 'once', 'is_mandatory' => true]);
        $this->assertSame(['Tenue'], $this->unpaidLabels());

        $this->pay([['item_type' => 'AUTRE_FRAIS', 'fee_type_id' => $feeId, 'period_month' => 10, 'paid_amount' => 5000]])->assertCreated();
        $this->assertSame([], $this->unpaidLabels());
    }
}
