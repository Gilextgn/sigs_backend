<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tranches\Models\TuitionInstallment;
use Tests\TestCase;

/** Découper la scolarité en plusieurs tranches d'un seul geste. */
class TrancheBulkTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_several_tranches_are_created_in_one_call(): void
    {
        $user = $this->userWithPermissions();
        $class = $this->createSchoolClass(180000);

        $this->actingAs($user)->postJson('/api/tranches/bulk', [
            'class_id' => $class->id,
            'tranches' => [
                ['label' => '1ère tranche', 'amount' => 80000, 'due_date' => '2026-10-31'],
                ['label' => '2ème tranche', 'amount' => 60000],
                ['label' => '3ème tranche', 'amount' => 40000],
            ],
        ])->assertCreated()->assertJsonCount(3);

        $this->assertSame(180000.0, (float) TuitionInstallment::where('class_id', $class->id)->sum('amount'));
    }

    public function test_going_over_the_tuition_creates_nothing_and_says_what_is_left(): void
    {
        $user = $this->userWithPermissions();
        $class = $this->createSchoolClass(100000);
        TuitionInstallment::create(['class_id' => $class->id, 'label' => 'Acompte', 'amount' => 30000]);

        $this->actingAs($user)->postJson('/api/tranches/bulk', [
            'class_id' => $class->id,
            'tranches' => [
                ['label' => '1ère tranche', 'amount' => 50000],
                ['label' => '2ème tranche', 'amount' => 50000],
            ],
        ])->assertUnprocessable()->assertJsonPath('errors.tranches.0', fn ($message) => str_contains($message, '70 000'));

        // Aucune des deux lignes n'a été enregistrée.
        $this->assertSame(1, TuitionInstallment::where('class_id', $class->id)->count());
    }

    public function test_it_needs_the_tranche_management_permission(): void
    {
        $class = $this->createSchoolClass(100000);

        $this->actingAs($this->userWithPermissions(['tranches.view']))->postJson('/api/tranches/bulk', [
            'class_id' => $class->id,
            'tranches' => [['label' => '1ère tranche', 'amount' => 50000]],
        ])->assertForbidden();
    }
}
