<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SchoolClasses\Models\SchoolClass;
use Modules\Students\Models\Guardian;
use Modules\Students\Models\Student;
use Modules\Tranches\Models\TuitionInstallment;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function createStudent(SchoolClass $class): Student
    {
        $guardian = Guardian::create([
            'full_name' => 'Bernard Amoussouga',
            'relationship_label' => 'Père',
            'phone' => '66552214',
        ]);

        return Student::create([
            'school_id' => 1,
            'class_id' => $class->id,
            'guardian_id' => $guardian->id,
            'registration_year' => (int) date('Y'),
            'registration_sequence' => 1,
            'matricule' => 'ELV-'.date('Y').'-000001',
            'first_name' => 'Hornel',
            'last_name' => 'Amoussouga',
            'status' => 'active',
        ]);
    }

    public function test_it_records_a_payment_against_a_tuition_installment(): void
    {
        $user = $this->userWithPermissions();
        $class = $this->createSchoolClass(300000);
        $student = $this->createStudent($class);
        $installment = TuitionInstallment::create([
            'class_id' => $class->id,
            'label' => '1ère tranche',
            'amount' => 100000,
        ]);

        $response = $this->actingAs($user)->postJson('/api/payments', [
            'student_id' => $student->id,
            'items' => [[
                'item_type' => 'TRANCHE',
                'tuition_installment_id' => $installment->id,
                'paid_amount' => 40000,
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('student_id', $student->id)
            ->assertJsonPath('cashier_user_id', $user->id);

        // total_paid_amount est casté en decimal:2, donc sérialisé en chaîne.
        $this->assertEqualsWithDelta(40000, (float) $response->json('total_paid_amount'), 0.001);

        $this->assertMatchesRegularExpression(
            '/^PAY-\d{8}-\d{6}$/',
            $response->json('reference_code'),
        );

        $this->assertDatabaseHas('payment_items', [
            'tuition_installment_id' => $installment->id,
            'item_type' => 'TRANCHE',
            'paid_amount' => 40000,
        ]);
    }

    public function test_it_rejects_a_payment_exceeding_the_installment_amount(): void
    {
        $user = $this->userWithPermissions();
        $class = $this->createSchoolClass(300000);
        $student = $this->createStudent($class);
        $installment = TuitionInstallment::create([
            'class_id' => $class->id,
            'label' => '1ère tranche',
            'amount' => 100000,
        ]);

        // Un premier encaissement consomme l'essentiel de la tranche...
        $this->actingAs($user)->postJson('/api/payments', [
            'student_id' => $student->id,
            'items' => [[
                'item_type' => 'TRANCHE',
                'tuition_installment_id' => $installment->id,
                'paid_amount' => 90000,
            ]],
        ])->assertCreated();

        // ...le second dépasse le reste dû et doit être refusé.
        $this->actingAs($user)->postJson('/api/payments', [
            'student_id' => $student->id,
            'items' => [[
                'item_type' => 'TRANCHE',
                'tuition_installment_id' => $installment->id,
                'paid_amount' => 20000,
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_it_rejects_an_installment_from_another_class(): void
    {
        $user = $this->userWithPermissions();
        $class = $this->createSchoolClass();
        $otherClass = $this->createSchoolClass();
        $student = $this->createStudent($class);
        $foreignInstallment = TuitionInstallment::create([
            'class_id' => $otherClass->id,
            'label' => 'Tranche d’une autre classe',
            'amount' => 50000,
        ]);

        $this->actingAs($user)->postJson('/api/payments', [
            'student_id' => $student->id,
            'items' => [[
                'item_type' => 'TRANCHE',
                'tuition_installment_id' => $foreignInstallment->id,
                'paid_amount' => 10000,
            ]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_it_forbids_a_payment_without_the_payments_create_permission(): void
    {
        $user = $this->userWithPermissions(['payments.view']);
        $class = $this->createSchoolClass();
        $student = $this->createStudent($class);
        $installment = TuitionInstallment::create([
            'class_id' => $class->id,
            'label' => '1ère tranche',
            'amount' => 100000,
        ]);

        $this->actingAs($user)->postJson('/api/payments', [
            'student_id' => $student->id,
            'items' => [[
                'item_type' => 'TRANCHE',
                'tuition_installment_id' => $installment->id,
                'paid_amount' => 10000,
            ]],
        ])->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_deleted_payment_frees_the_amount_for_a_new_payment(): void
    {
        /** @var User $user */
        $user = $this->userWithPermissions();
        $class = $this->createSchoolClass(300000);
        $student = $this->createStudent($class);
        $installment = TuitionInstallment::create([
            'class_id' => $class->id,
            'label' => '1ère tranche',
            'amount' => 100000,
        ]);

        $paymentId = $this->actingAs($user)->postJson('/api/payments', [
            'student_id' => $student->id,
            'items' => [[
                'item_type' => 'TRANCHE',
                'tuition_installment_id' => $installment->id,
                'paid_amount' => 100000,
            ]],
        ])->assertCreated()->json('id');

        $this->actingAs($user)->deleteJson("/api/payments/{$paymentId}", ['reason' => 'Erreur de saisie'])->assertNoContent();

        // La tranche est de nouveau entièrement encaissable après annulation.
        $this->actingAs($user)->postJson('/api/payments', [
            'student_id' => $student->id,
            'items' => [[
                'item_type' => 'TRANCHE',
                'tuition_installment_id' => $installment->id,
                'paid_amount' => 100000,
            ]],
        ])->assertCreated();
    }

    public function test_a_partial_payment_is_an_acompte_until_the_line_is_settled(): void
    {
        $user = $this->userWithPermissions();
        $class = $this->createSchoolClass(300000);
        $student = $this->createStudent($class);
        $installment = TuitionInstallment::create([
            'class_id' => $class->id,
            'label' => '1ère tranche',
            'amount' => 100000,
        ]);

        $pay = fn (int $amount) => $this->actingAs($user)->postJson('/api/payments', [
            'student_id' => $student->id,
            'items' => [[
                'item_type' => 'TRANCHE',
                'tuition_installment_id' => $installment->id,
                'paid_amount' => $amount,
            ]],
        ])->assertCreated();

        $first = $pay(40000);
        $first->assertJsonPath('is_partial', true)
            ->assertJsonPath('items.0.line_status', 'partial')
            ->assertJsonPath('items.0.label', '1ère tranche');
        $this->assertEqualsWithDelta(60000, $first->json('items.0.remaining_after'), 0.001);

        $second = $pay(60000);
        $second->assertJsonPath('is_partial', false)
            ->assertJsonPath('items.0.line_status', 'settled');
        $this->assertEqualsWithDelta(100000, $second->json('items.0.paid_to_date'), 0.001);

        // Réimprimé plus tard, le premier reçu montre toujours la situation
        // du jour où il a été émis : un acompte, avec 60 000 restants.
        $this->actingAs($user)->getJson('/api/payments/'.$first->json('id'))
            ->assertJsonPath('items.0.line_status', 'partial')
            ->assertJsonPath('items.0.remaining_after', 60000);

        $list = $this->actingAs($user)->getJson('/api/payments')->assertOk();
        $this->assertSame('1ère tranche', $list->json('data.0.items.0.label'));
        $this->assertSame(['partial', 'settled'], collect($list->json('data'))->sortBy('id')->pluck('items.0.line_status')->values()->all());
    }

    public function test_home_top_bar_and_debtors_list_report_the_same_outstanding(): void
    {
        $user = $this->userWithPermissions();
        $class = $this->createSchoolClass(300000);
        $student = $this->createStudent($class);
        $installment = TuitionInstallment::create([
            'class_id' => $class->id,
            'label' => '1ère tranche',
            'amount' => 100000,
        ]);

        $this->actingAs($user)->postJson('/api/payments', [
            'student_id' => $student->id,
            'items' => [[
                'item_type' => 'TRANCHE',
                'tuition_installment_id' => $installment->id,
                'paid_amount' => 40000,
            ]],
        ])->assertCreated();

        $debtorsTotal = collect($this->actingAs($user)->getJson('/api/debtors')->assertOk()->json())->sum('outstanding_amount');
        $summary = $this->actingAs($user)->getJson('/api/dashboard/summary')->assertOk();
        $top = $this->actingAs($user)->getJson('/api/dashboard/top-debtors?limit=1')->assertOk();

        $this->assertEqualsWithDelta(260000, $debtorsTotal, 0.001);
        $this->assertEqualsWithDelta($debtorsTotal, $summary->json('outstanding'), 0.001);
        $this->assertEqualsWithDelta($debtorsTotal, $top->json('total_outstanding'), 0.001);
        $this->assertSame($summary->json('debtors'), $top->json('debtors_count'));
        $this->assertCount(1, $top->json('items'));
        $this->assertSame('partial', $top->json('items.0.unpaid_items.0.status'));
    }

    private function payOneInstallment(User $user, Student $student, SchoolClass $class)
    {
        $installment = TuitionInstallment::create(['class_id' => $class->id, 'label' => '1ère tranche', 'amount' => 100000]);

        return $this->actingAs($user)->postJson('/api/payments', [
            'student_id' => $student->id,
            'items' => [['item_type' => 'TRANCHE', 'tuition_installment_id' => $installment->id, 'paid_amount' => 40000]],
        ])->assertCreated();
    }

    public function test_the_receipt_is_emailed_to_the_guardian_and_traced(): void
    {
        config(['mail.default' => 'array']);
        $user = $this->userWithPermissions();
        $class = $this->createSchoolClass(300000);
        $student = $this->createStudent($class);
        $student->guardian->update(['email' => 'parent@example.com']);

        $payment = $this->payOneInstallment($user, $student, $class);

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString($payment->json('reference_code'), $messages[0]->getOriginalMessage()->getSubject());

        $this->assertDatabaseHas('receipt_deliveries', [
            'payment_id' => $payment->json('id'),
            'channel' => 'email',
            'status' => 'sent',
            'recipient' => 'pa***@example.com',
        ]);

        $this->actingAs($user)->getJson('/api/payments/'.$payment->json('id'))
            ->assertOk()
            ->assertJsonPath('deliveries.0.channel', 'email')
            ->assertJsonPath('receipt_contacts.email', 'parent@example.com');
    }

    public function test_no_delivery_is_attempted_without_guardian_contacts(): void
    {
        $user = $this->userWithPermissions();
        $class = $this->createSchoolClass(300000);
        $student = $this->createStudent($class);

        $payment = $this->payOneInstallment($user, $student, $class);

        $this->assertDatabaseMissing('receipt_deliveries', ['payment_id' => $payment->json('id')]);
        $this->actingAs($user)->postJson('/api/payments/'.$payment->json('id').'/send-receipt')->assertStatus(422);
    }

    public function test_anyone_can_verify_a_receipt_and_see_its_cancellation(): void
    {
        $user = $this->userWithPermissions();
        $class = $this->createSchoolClass(300000);
        $student = $this->createStudent($class);
        $payment = $this->payOneInstallment($user, $student, $class);
        $token = $payment->json('verification_token');

        $this->assertSame(40, strlen($token));
        // Le jeton n'apparaît pas dans la liste des paiements.
        $this->actingAs($user)->getJson('/api/payments')->assertJsonMissingPath('data.0.verification_token');

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/receipts/verify/{$token}")
            ->assertOk()
            ->assertJsonPath('status', 'valid')
            ->assertJsonPath('reference_code', $payment->json('reference_code'))
            ->assertJsonPath('student', 'Hornel A.');

        $this->actingAs($user)->deleteJson('/api/payments/'.$payment->json('id'), ['reason' => 'Erreur de saisie'])->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/receipts/verify/{$token}")->assertJsonPath('status', 'cancelled');

        $this->getJson('/api/receipts/verify/'.str_repeat('x', 40))->assertNotFound();
    }

    public function test_whatsapp_numbers_are_normalised_with_the_country_code(): void
    {
        $normalize = [\Modules\Payments\Services\ReceiptNotifier::class, 'normalizePhone'];

        $this->assertSame('2290191489743', $normalize('01 91 48 97 43'));
        $this->assertSame('2290191489743', $normalize('+229 01 91 48 97 43'));
        $this->assertSame('2290191489743', $normalize('00229 0191489743'));
        $this->assertSame('2290191489743', $normalize('2290191489743'));
    }
}
