<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SchoolClasses\Models\SchoolClass;
use Modules\Students\Models\Guardian;
use Modules\Students\Models\Student;
use Modules\Tranches\Models\TuitionInstallment;
use Tests\TestCase;

class CashClosingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private TuitionInstallment $installment;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $class = $this->createSchoolClass(300000);
        $this->installment = TuitionInstallment::create(['class_id' => $class->id, 'label' => '1ère tranche', 'amount' => 100000]);
        $this->student = $this->createStudent($class);
    }

    private function createStudent(SchoolClass $class): Student
    {
        $guardian = Guardian::create(['full_name' => 'Parent Test', 'relationship_label' => 'Mère', 'phone' => '66000000']);

        return Student::create([
            'school_id' => 1, 'class_id' => $class->id, 'guardian_id' => $guardian->id,
            'registration_year' => (int) date('Y'), 'registration_sequence' => 1,
            'matricule' => 'ELV-'.date('Y').'-000001', 'first_name' => 'Awa', 'last_name' => 'Dossou', 'status' => 'active',
        ]);
    }

    private function pay(User $user, float $amount)
    {
        return $this->actingAs($user)->postJson('/api/payments', [
            'student_id' => $this->student->id,
            'items' => [['item_type' => 'TRANCHE', 'tuition_installment_id' => $this->installment->id, 'paid_amount' => $amount]],
        ]);
    }

    private function cashier(): User
    {
        return $this->userWithPermissions(['payments.create', 'payments.view', 'payments.delete', 'cash.close']);
    }

    public function test_report_groups_payments_by_class_and_student(): void
    {
        $cashier = $this->cashier();
        $this->pay($cashier, 30000)->assertCreated();
        $this->pay($cashier, 20000)->assertCreated();

        $response = $this->actingAs($cashier)->getJson('/api/cash/report')->assertOk();

        $response->assertJsonPath('payment_count', 2)
            ->assertJsonPath('by_class.0.class', 'Classe de test')
            ->assertJsonPath('by_class.0.students.0.full_name', 'Dossou Awa')
            ->assertJsonCount(2, 'by_class.0.students.0.payments')
            ->assertJsonPath('by_class.0.students.0.payments.0.lines.0.label', '1ère tranche')
            ->assertJsonPath('my_day.payment_count', 2)
            ->assertJsonPath('my_day.closed', false);
        $this->assertEqualsWithDelta(50000, $response->json('total_amount'), 0.001);
    }

    public function test_a_cashier_without_cash_report_only_sees_their_own_cash(): void
    {
        $cashier = $this->cashier();
        $other = $this->cashier();
        $this->pay($other, 30000)->assertCreated();

        $this->actingAs($cashier)->getJson('/api/cash/report?cashier_id='.$other->id)
            ->assertOk()
            ->assertJsonPath('payment_count', 0);

        $this->actingAs($this->userWithPermissions())->getJson('/api/cash/report')
            ->assertOk()
            ->assertJsonPath('payment_count', 1);
    }

    public function test_closing_freezes_the_day(): void
    {
        $cashier = $this->cashier();
        $paymentId = $this->pay($cashier, 30000)->json('id');

        $this->actingAs($cashier)->postJson('/api/cash/closings', ['counted_amount' => 30000])
            ->assertCreated()
            ->assertJsonPath('payment_count', 1);

        // Plus d'encaissement, plus de suppression, pas de seconde clôture.
        $this->pay($cashier, 10000)->assertStatus(422);
        $this->actingAs($cashier)->deleteJson("/api/payments/{$paymentId}", ['reason' => 'Erreur de saisie'])->assertStatus(422);
        $this->actingAs($cashier)->postJson('/api/cash/closings', ['counted_amount' => 30000])->assertStatus(422);
        $this->assertDatabaseHas('audit_logs', ['action_code' => 'cash.closed']);
    }

    public function test_a_difference_must_be_explained(): void
    {
        $cashier = $this->cashier();
        $this->pay($cashier, 30000)->assertCreated();

        $this->actingAs($cashier)->postJson('/api/cash/closings', ['counted_amount' => 25000])
            ->assertStatus(422)->assertJsonValidationErrors('note');

        $this->actingAs($cashier)->postJson('/api/cash/closings', ['counted_amount' => 25000, 'note' => 'Monnaie rendue en trop'])
            ->assertCreated();
        $this->assertDatabaseHas('cash_closings', ['cashier_user_id' => $cashier->id, 'difference' => -5000]);
    }

    public function test_only_reopen_permission_can_reopen_with_a_reason(): void
    {
        $cashier = $this->cashier();
        $this->pay($cashier, 30000)->assertCreated();
        $closingId = $this->actingAs($cashier)->postJson('/api/cash/closings', ['counted_amount' => 30000])->json('id');

        $this->actingAs($cashier)->postJson("/api/cash/closings/{$closingId}/reopen", ['reason' => 'Oubli'])->assertForbidden();

        $admin = $this->userWithPermissions();
        $this->actingAs($admin)->postJson("/api/cash/closings/{$closingId}/reopen", [])->assertStatus(422);
        $this->actingAs($admin)->postJson("/api/cash/closings/{$closingId}/reopen", ['reason' => 'Paiement oublié'])->assertOk();

        // Caisse rouverte : le caissier peut de nouveau encaisser.
        $this->pay($cashier, 10000)->assertCreated();
        $this->assertDatabaseHas('audit_logs', ['action_code' => 'cash.reopened']);
    }

    public function test_deleting_a_payment_requires_a_reason_and_is_listed_in_the_report(): void
    {
        $cashier = $this->cashier();
        $paymentId = $this->pay($cashier, 30000)->json('id');

        $this->actingAs($cashier)->deleteJson("/api/payments/{$paymentId}")->assertStatus(422);
        $this->actingAs($cashier)->deleteJson("/api/payments/{$paymentId}", ['reason' => 'Mauvais élève'])->assertNoContent();

        $this->actingAs($cashier)->getJson('/api/cash/report')
            ->assertOk()
            ->assertJsonPath('payment_count', 0)
            ->assertJsonPath('cancellations.0.reason', 'Mauvais élève')
            ->assertJsonPath('cancellations.0.deleted_by', $cashier->full_name);
    }
}
