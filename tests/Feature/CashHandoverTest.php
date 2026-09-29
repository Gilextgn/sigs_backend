<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SchoolClasses\Models\SchoolClass;
use Modules\Students\Models\Guardian;
use Modules\Students\Models\Student;
use Modules\Tranches\Models\TuitionInstallment;
use Tests\TestCase;

/**
 * Point de caisse et remise de caisse : la secrétaire encaisse, puis remet
 * l'argent au directeur, qui saisit ce qu'il a reçu.
 */
class CashHandoverTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private TuitionInstallment $installment;

    private Student $student;

    private User $director;

    private User $secretary;

    protected function setUp(): void
    {
        parent::setUp();

        $class = $this->createSchoolClass(300000);
        $this->installment = TuitionInstallment::create(['class_id' => $class->id, 'label' => '1ère tranche', 'amount' => 100000]);
        $this->student = $this->createStudent($class);
        $this->director = $this->userWithPermissions();
        $this->secretary = $this->userWithPermissions(['payments.create', 'payments.view', 'payments.delete']);
    }

    private function createStudent(SchoolClass $class): Student
    {
        $guardian = Guardian::create(['full_name' => 'Parent Test', 'relationship_label' => 'Mère', 'phone' => '0166000000']);

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
        ])->assertCreated();
    }

    public function test_report_groups_payments_by_class_and_shows_what_is_not_yet_handed_over(): void
    {
        $this->pay($this->secretary, 30000);
        $this->pay($this->secretary, 20000);

        $this->actingAs($this->secretary)->getJson('/api/cash/report')->assertOk()
            ->assertJsonPath('payment_count', 2)
            ->assertJsonPath('by_class.0.students.0.full_name', 'Dossou Awa')
            ->assertJsonPath('my_pending.payment_count', 2)
            ->assertJsonPath('my_pending.expected_amount', 50000);
    }

    public function test_the_director_receives_the_secretarys_cash(): void
    {
        $firstId = $this->pay($this->secretary, 30000)->json('id');
        $this->pay($this->secretary, 20000);

        $this->actingAs($this->director)->getJson('/api/cash/pending')->assertOk()
            ->assertJsonPath('0.cashier_id', $this->secretary->id)
            ->assertJsonPath('0.expected_amount', 50000);

        // La secrétaire ne peut pas enregistrer elle-même la remise.
        $this->actingAs($this->secretary)->postJson('/api/cash/handovers', ['cashier_user_id' => $this->secretary->id, 'received_amount' => 50000])->assertForbidden();

        // Écart : il faut l'expliquer.
        $this->actingAs($this->director)->postJson('/api/cash/handovers', ['cashier_user_id' => $this->secretary->id, 'received_amount' => 45000])
            ->assertStatus(422)->assertJsonValidationErrors('note');

        $handover = $this->actingAs($this->director)->postJson('/api/cash/handovers', ['cashier_user_id' => $this->secretary->id, 'received_amount' => 45000, 'note' => 'Billet manquant'])
            ->assertCreated()
            ->assertJsonPath('payment_count', 2)
            ->assertJsonPath('difference', -5000)
            ->assertJsonCount(2, 'payments');

        // Remis : plus rien en attente, et ces paiements ne peuvent plus être annulés.
        $this->actingAs($this->director)->getJson('/api/cash/pending')->assertJsonCount(0);
        $this->actingAs($this->secretary)->deleteJson("/api/payments/{$firstId}", ['reason' => 'Erreur de saisie'])->assertStatus(422);

        // L'encaissement continue : le paiement suivant ira dans la prochaine remise.
        $this->pay($this->secretary, 10000);
        $this->actingAs($this->director)->getJson('/api/cash/pending')->assertJsonPath('0.expected_amount', 10000);

        // La secrétaire peut consulter son bordereau.
        $this->actingAs($this->secretary)->getJson('/api/cash/handovers/'.$handover->json('id'))->assertOk()->assertJsonPath('received_by', $this->director->full_name);
        $this->assertDatabaseHas('audit_logs', ['action_code' => 'cash.handover']);
    }

    public function test_deleting_a_payment_requires_a_reason_and_is_listed_in_the_report(): void
    {
        $paymentId = $this->pay($this->secretary, 30000)->json('id');

        $this->actingAs($this->secretary)->deleteJson("/api/payments/{$paymentId}")->assertStatus(422);
        $this->actingAs($this->secretary)->deleteJson("/api/payments/{$paymentId}", ['reason' => 'Mauvais élève'])->assertNoContent();

        $this->actingAs($this->secretary)->getJson('/api/cash/report')
            ->assertOk()
            ->assertJsonPath('payment_count', 0)
            ->assertJsonPath('cancellations.0.reason', 'Mauvais élève');
    }

    public function test_a_missing_permission_gets_a_readable_message(): void
    {
        $this->actingAs($this->secretary)->postJson('/api/teachers', ['full_name' => 'X'])
            ->assertForbidden()
            ->assertJsonPath('code', 'permission_denied');
        $this->assertStringContainsString("Votre compte n'a pas le droit de", $this->actingAs($this->secretary)->postJson('/api/teachers', [])->json('message'));
    }
}
