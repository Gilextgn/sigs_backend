<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Students\Models\Guardian;
use Modules\Students\Models\Student;
use Modules\Tranches\Models\TuitionInstallment;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $admin;

    private User $cashier;

    private Student $student;

    private TuitionInstallment $installment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->userWithPermissions();
        $this->cashier = $this->userWithPermissions(['payments.create', 'payments.view', 'payments.delete', 'cash.close']);
        $this->cashier->update(['full_name' => 'Joséphine DELAPOCHE']);
        $class = $this->createSchoolClass();
        $this->installment = TuitionInstallment::create(['class_id' => $class->id, 'label' => '1ère tranche', 'amount' => 100000]);
        $guardian = Guardian::create(['full_name' => 'P', 'relationship_label' => 'Mère', 'phone' => '0166000000']);
        $this->student = Student::create([
            'school_id' => 1, 'class_id' => $class->id, 'guardian_id' => $guardian->id, 'registration_year' => 2026, 'registration_sequence' => 1,
            'matricule' => 'ELV-1', 'first_name' => 'Arielle', 'last_name' => 'ZINGBE', 'status' => 'active',
        ]);
    }

    private function pay(User $user)
    {
        return $this->actingAs($user)->postJson('/api/payments', [
            'student_id' => $this->student->id,
            'items' => [['item_type' => 'TRANCHE', 'tuition_installment_id' => $this->installment->id, 'paid_amount' => 30000]],
        ])->assertCreated();
    }

    public function test_payment_actions_by_a_non_admin_notify_the_admins(): void
    {
        $paymentId = $this->pay($this->cashier)->json('id');
        $this->actingAs($this->cashier)->deleteJson("/api/payments/{$paymentId}", ['reason' => 'Mauvais élève'])->assertNoContent();

        $response = $this->actingAs($this->admin)->getJson('/api/notifications')->assertOk()->assertJsonPath('unread_count', 2);
        $this->assertSame('Paiement annulé par Joséphine DELAPOCHE', $response->json('data.0.title'));
        $this->assertStringContainsString('motif : Mauvais élève', $response->json('data.0.body'));
        $this->assertSame('Encaissement de 30 000 XOF par Joséphine DELAPOCHE', $response->json('data.1.title'));
        $this->assertStringContainsString('ZINGBE Arielle', $response->json('data.1.body'));

        // Le caissier ne voit pas les notifications des administrateurs.
        $this->actingAs($this->cashier)->getJson('/api/notifications')->assertJsonPath('unread_count', 0);

        $this->actingAs($this->admin)->postJson('/api/notifications/'.$response->json('data.0.id').'/read')->assertNoContent();
        $this->actingAs($this->admin)->getJson('/api/notifications')->assertJsonPath('unread_count', 1);
        $this->actingAs($this->admin)->postJson('/api/notifications/read-all')->assertNoContent();
        $this->actingAs($this->admin)->getJson('/api/notifications')->assertJsonPath('unread_count', 0);
    }

    public function test_an_admin_own_actions_are_not_notified(): void
    {
        $this->pay($this->admin);

        $this->actingAs($this->admin)->getJson('/api/notifications')->assertJsonPath('unread_count', 0);
        $this->assertDatabaseHas('audit_logs', ['action_code' => 'payment.created']);
    }

    public function test_a_cash_closing_by_a_cashier_is_notified(): void
    {
        $this->pay($this->cashier);
        $this->actingAs($this->cashier)->postJson('/api/cash/closings', ['counted_amount' => 25000, 'note' => 'Billet manquant'])->assertCreated();

        $this->actingAs($this->admin)->getJson('/api/notifications')
            ->assertJsonPath('data.0.title', 'Caisse du jour clôturée par Joséphine DELAPOCHE')
            ->assertJsonPath('data.0.body', 'Attendu 30 000 XOF · compté 25 000 XOF · écart -5 000 XOF');
    }
}
