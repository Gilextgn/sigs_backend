<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SchoolClasses\Models\SchoolClass;
use Modules\SchoolClasses\Models\SchoolCycle;
use Modules\Students\Models\Guardian;
use Modules\Students\Models\Student;
use Modules\Tranches\Models\TuitionInstallment;
use Tests\TestCase;

class ClassGroupTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function createClass(array $data)
    {
        return $this->actingAs($this->userWithPermissions())->postJson('/api/classes', [
            'cycle_id' => SchoolCycle::where('code', 'primaire')->value('id'),
            'code' => 'C-'.uniqid(),
            ...$data,
        ]);
    }

    private function student(int $classId): Student
    {
        $guardian = Guardian::create(['full_name' => 'Parent', 'relationship_label' => 'Père', 'phone' => '66000001']);

        return Student::create([
            'school_id' => 1, 'class_id' => $classId, 'guardian_id' => $guardian->id,
            'registration_year' => (int) date('Y'), 'registration_sequence' => random_int(1, 99999),
            'matricule' => 'ELV-'.uniqid(), 'first_name' => 'Koffi', 'last_name' => 'Agbo', 'status' => 'active',
        ]);
    }

    public function test_a_group_inherits_tuition_tranches_and_fees_from_its_main_class(): void
    {
        $admin = $this->userWithPermissions();
        $mainId = $this->createClass(['label' => 'CE1 A', 'tuition_amount' => 150000])->assertCreated()->json('id');
        $groupId = $this->createClass(['label' => 'CE1 B', 'parent_class_id' => $mainId])
            ->assertCreated()
            ->assertJsonPath('parent.label', 'CE1 A')
            ->json('id');
        $this->assertEquals(150000, (float) SchoolClass::find($groupId)->tuition_amount);

        // Tranche saisie sur le groupe = tranche de la classe principale, commune aux deux.
        $this->actingAs($admin)->postJson('/api/tranches', ['class_id' => $groupId, 'label' => '1ère tranche', 'amount' => 50000])->assertSuccessful();
        $this->assertDatabaseHas('tuition_installments', ['class_id' => $mainId, 'label' => '1ère tranche']);
        $this->actingAs($admin)->getJson("/api/tranches?class_id={$groupId}")->assertOk()->assertJsonCount(1);

        // Frais affecté au groupe = affecté à la classe principale.
        $this->actingAs($admin)->postJson('/api/fees', ['label' => 'Tenue', 'amount' => 5000, 'class_ids' => [$groupId]])->assertSuccessful();
        $this->assertDatabaseHas('fee_type_classes', ['class_id' => $mainId]);

        // Un élève de CE1 B paie la tranche commune et doit le frais de la classe principale.
        $student = $this->student($groupId);
        $installmentId = TuitionInstallment::where('class_id', $mainId)->value('id');
        $this->actingAs($admin)->postJson('/api/payments', [
            'student_id' => $student->id,
            'items' => [['item_type' => 'TRANCHE', 'tuition_installment_id' => $installmentId, 'paid_amount' => 20000]],
        ])->assertCreated();

        $balance = $this->actingAs($admin)->getJson("/api/students/{$student->id}/balance")->assertOk();
        $this->assertEqualsWithDelta(150000, $balance->json('theoretical_amount'), 0.001);
        $this->assertEqualsWithDelta(20000, $balance->json('paid_amount'), 0.001);
        $this->assertEqualsCanonicalizing(['1ère tranche', 'Tenue'], collect($balance->json('unpaid_items'))->pluck('label')->all());
    }

    public function test_main_class_tuition_change_applies_to_its_groups(): void
    {
        $main = $this->createClass(['label' => 'CM2 A', 'tuition_amount' => 100000])->json();
        $groupId = $this->createClass(['label' => 'CM2 B', 'parent_class_id' => $main['id']])->json('id');

        $this->actingAs($this->userWithPermissions())->putJson("/api/classes/{$main['id']}", [
            'cycle_id' => $main['cycle_id'], 'code' => $main['code'], 'label' => 'CM2 A', 'tuition_amount' => 120000,
        ])->assertOk();

        $this->assertEquals(120000, (float) SchoolClass::find($groupId)->tuition_amount);
    }

    public function test_group_rules(): void
    {
        $mainId = $this->createClass(['label' => 'CP1', 'tuition_amount' => 100000])->json('id');
        $groupId = $this->createClass(['label' => 'CP2', 'parent_class_id' => $mainId])->json('id');

        // Pas de groupe de groupe.
        $this->createClass(['label' => 'CP3', 'parent_class_id' => $groupId])->assertStatus(422);
        // Une classe principale ayant des groupes ne se supprime pas.
        $this->actingAs($this->userWithPermissions())->deleteJson("/api/classes/{$mainId}")->assertStatus(422);

        // Une classe qui a ses propres tranches ne peut pas devenir un groupe.
        $other = $this->createClass(['label' => 'CPA', 'tuition_amount' => 90000])->json();
        TuitionInstallment::create(['class_id' => $other['id'], 'label' => 'T1', 'amount' => 30000]);
        $this->actingAs($this->userWithPermissions())->putJson("/api/classes/{$other['id']}", [
            'cycle_id' => $other['cycle_id'], 'code' => $other['code'], 'label' => 'CPA', 'parent_class_id' => $mainId,
        ])->assertStatus(422);
    }

    public function test_a_group_is_listed_right_after_its_main_class(): void
    {
        foreach (['CI', 'CE1 A', 'CM1'] as $label) {
            $this->createClass(['label' => $label, 'tuition_amount' => 100000]);
        }
        $this->createClass(['label' => 'Groupe 2', 'parent_class_id' => SchoolClass::where('label', 'CE1 A')->value('id')]);

        $labels = collect($this->actingAs($this->userWithPermissions())->getJson('/api/classes')->json())->pluck('label')->all();
        $this->assertSame(['CI', 'CE1 A', 'Groupe 2', 'CM1'], $labels);
    }
}
