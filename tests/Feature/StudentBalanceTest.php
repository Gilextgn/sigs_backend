<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SchoolClasses\Models\SchoolClass;
use Modules\Students\Models\Guardian;
use Modules\Students\Models\Student;
use Modules\Tranches\Models\TuitionInstallment;
use Tests\TestCase;

/**
 * Le solde d'un élève quand les tranches de sa classe ne couvrent pas toute
 * la scolarité : le reste dû existe bel et bien, et la fiche doit pouvoir le
 * dire au lieu d'annoncer « tout est réglé ».
 */
class StudentBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function createStudent(SchoolClass $class): Student
    {
        $guardian = Guardian::create(['full_name' => 'Teddy Zingbe', 'relationship_label' => 'Père', 'phone' => '0155414787']);

        return Student::create([
            'school_id' => 1,
            'class_id' => $class->id,
            'guardian_id' => $guardian->id,
            'registration_year' => (int) date('Y'),
            'registration_sequence' => 1,
            'matricule' => 'ELV-'.date('Y').'-000004',
            'first_name' => 'Arielle',
            'last_name' => 'Zingbe',
            'status' => 'active',
        ]);
    }

    public function test_the_part_no_installment_covers_is_reported_as_unlisted(): void
    {
        $user = $this->userWithPermissions();
        $class = $this->createSchoolClass(185000);
        $student = $this->createStudent($class);

        // Une seule tranche définie, très en deçà de la scolarité, et soldée.
        $installment = TuitionInstallment::create(['class_id' => $class->id, 'label' => '1ère tranche', 'amount' => 55000]);
        $this->actingAs($user)->postJson('/api/payments', [
            'student_id' => $student->id,
            'items' => [['item_type' => 'TRANCHE', 'tuition_installment_id' => $installment->id, 'paid_amount' => 55000]],
        ])->assertCreated();

        $this->actingAs($user)->getJson("/api/students/{$student->id}/balance")->assertOk()
            ->assertJsonPath('theoretical_amount', 185000)
            ->assertJsonPath('paid_amount', 55000)
            ->assertJsonPath('outstanding_amount', 130000)
            // Aucune ligne à encaisser : c'est exactement le cas qui affichait 0.
            ->assertJsonCount(0, 'unpaid_items')
            ->assertJsonPath('unlisted_amount', 130000);
    }

    public function test_installments_covering_the_tuition_leave_nothing_unlisted(): void
    {
        $user = $this->userWithPermissions();
        $class = $this->createSchoolClass(100000);
        $student = $this->createStudent($class);

        foreach ([['1ère tranche', 60000], ['2ème tranche', 40000]] as [$label, $amount]) {
            TuitionInstallment::create(['class_id' => $class->id, 'label' => $label, 'amount' => $amount]);
        }

        $first = TuitionInstallment::where('class_id', $class->id)->orderBy('id')->first();
        $this->actingAs($user)->postJson('/api/payments', [
            'student_id' => $student->id,
            'items' => [['item_type' => 'TRANCHE', 'tuition_installment_id' => $first->id, 'paid_amount' => 25000]],
        ])->assertCreated();

        $this->actingAs($user)->getJson("/api/students/{$student->id}/balance")->assertOk()
            ->assertJsonPath('outstanding_amount', 75000)
            ->assertJsonCount(2, 'unpaid_items')
            ->assertJsonPath('unlisted_amount', 0);
    }
}
