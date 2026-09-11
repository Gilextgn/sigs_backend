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

        $this->actingAs($user)->deleteJson("/api/payments/{$paymentId}")->assertNoContent();

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
}
