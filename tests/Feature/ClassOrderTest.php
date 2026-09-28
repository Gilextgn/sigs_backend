<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SchoolClasses\Models\SchoolCycle;
use Modules\SchoolClasses\Support\PedagogicalOrder;
use Tests\TestCase;

class ClassOrderTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function createClass(string $label, string $cycleCode = 'primaire')
    {
        return $this->actingAs($this->userWithPermissions())->postJson('/api/classes', [
            'cycle_id' => SchoolCycle::where('code', $cycleCode)->value('id'),
            'code' => 'C-'.uniqid(),
            'label' => $label,
            'tuition_amount' => 100000,
        ])->assertCreated();
    }

    private function labels(): array
    {
        return collect($this->actingAs($this->userWithPermissions())->getJson('/api/classes')->assertOk()->json())->pluck('label')->all();
    }

    public function test_guesses_the_pedagogical_rank_from_the_label(): void
    {
        $this->assertLessThan(PedagogicalOrder::rank('CP'), PedagogicalOrder::rank('CI'));
        $this->assertLessThan(PedagogicalOrder::rank('CE2'), PedagogicalOrder::rank('CE1 B'));
        $this->assertLessThan(PedagogicalOrder::rank('5ème'), PedagogicalOrder::rank('6ème A'));
        $this->assertLessThan(PedagogicalOrder::rank('Tle D'), PedagogicalOrder::rank('2nde C'));
    }

    public function test_new_classes_are_placed_in_pedagogical_order_not_alphabetical(): void
    {
        foreach (['CM2', 'CE1 A', 'CI', 'CP', '6ème'] as $label) {
            $this->createClass($label, $label === '6ème' ? 'cycle_1' : 'primaire');
        }
        $this->createClass('CE1 B');

        $this->assertSame(['CI', 'CP', 'CE1 A', 'CE1 B', 'CM2', '6ème'], $this->labels());
    }

    public function test_the_director_can_reorder_classes_manually(): void
    {
        $ids = collect(['CI', 'CP', 'CE1'])->map(fn ($label) => $this->createClass($label)->json('id'))->all();

        $this->actingAs($this->userWithPermissions())
            ->putJson('/api/classes/reorder', ['ids' => [$ids[2], $ids[0], $ids[1]]])
            ->assertOk()
            ->assertJsonPath('0.label', 'CE1');
        $this->assertSame(['CE1', 'CI', 'CP'], $this->labels());

        // Liste incomplète : refusée plutôt que de laisser des trous dans l'ordre.
        $this->actingAs($this->userWithPermissions())->putJson('/api/classes/reorder', ['ids' => [$ids[0]]])->assertStatus(422);
        $this->actingAs($this->userWithPermissions(['classes.view']))->putJson('/api/classes/reorder', ['ids' => $ids])->assertForbidden();
    }
}
