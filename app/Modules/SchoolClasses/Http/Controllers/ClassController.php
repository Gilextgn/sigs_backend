<?php

namespace Modules\SchoolClasses\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\SchoolClasses\Http\Requests\StoreClassRequest;
use Modules\SchoolClasses\Models\SchoolClass;
use Modules\SchoolClasses\Models\SchoolCycle;
use Modules\SchoolClasses\Support\PedagogicalOrder;

class ClassController extends Controller
{
    public function index(Request $request)
    {
        return SchoolClass::with('cycle', 'parent:id,label')
            // Effectif actif : affiché sur les cadres de classe de l'écran Élèves.
            ->withCount(['students as students_count' => fn ($q) => $q->where('status', 'active')])
            ->when($request->search, fn ($q, $s) => $q->where('label', 'like', "%{$s}%"))
            ->ordered()
            ->get();
    }

    public function store(StoreClassRequest $request)
    {
        return response()->json(DB::transaction(function () use ($request) {
            $data = $this->withGroupRules($request->validated());
            $class = SchoolClass::create($data);
            $this->placeNewClass($class);

            return $class->fresh(['cycle', 'parent:id,label']);
        }), 201);
    }

    /** Ordre manuel : la liste complète des classes, de la plus petite à la plus grande. */
    public function reorder(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'distinct']]);
        $known = SchoolClass::pluck('id')->all();
        abort_if(array_diff($data['ids'], $known) !== [] || count($data['ids']) !== count($known), 422, 'La liste des classes est incomplète ou a changé : rechargez la page.');

        DB::transaction(fn () => $this->applyOrder($data['ids']));

        return SchoolClass::with('cycle', 'parent:id,label')->ordered()->get();
    }

    public function show(SchoolClass $class)
    {
        return $class->load('cycle', 'installments', 'parent:id,label');
    }

    public function update(StoreClassRequest $request, SchoolClass $class)
    {
        DB::transaction(function () use ($request, $class) {
            $class->update($this->withGroupRules($request->validated(), $class));

            // La scolarité d'une classe principale vaut pour tous ses groupes.
            if (! $class->parent_class_id) {
                $class->groups()->update(['tuition_amount' => $class->tuition_amount]);
            }
        });

        return $class->fresh(['cycle', 'parent:id,label']);
    }

    public function destroy(SchoolClass $class)
    {
        abort_if($class->students()->exists(), 422, 'Impossible de supprimer une classe qui a des élèves.');
        abort_if($class->groups()->exists(), 422, 'Cette classe a des groupes (ex. CE1 B) : supprimez-les ou rattachez-les ailleurs d’abord.');
        $class->delete();

        return response()->noContent();
    }

    /**
     * Un groupe n'a qu'un niveau (pas de groupe de groupe) et ne porte aucun
     * tarif propre : sa scolarité est celle de la classe principale.
     */
    private function withGroupRules(array $data, ?SchoolClass $class = null): array
    {
        $parentId = $data['parent_class_id'] ?? null;

        if (! $parentId) {
            abort_if(! isset($data['tuition_amount']), 422, 'Indiquez la scolarité de la classe.');

            return [...$data, 'parent_class_id' => null];
        }

        $parent = SchoolClass::findOrFail($parentId);
        abort_if($class && $parent->id === $class->id, 422, 'Une classe ne peut pas être son propre groupe.');
        abort_if($parent->parent_class_id !== null, 422, "« {$parent->label} » est déjà un groupe : rattachez à sa classe principale.");

        if ($class) {
            abort_if($class->groups()->exists(), 422, 'Cette classe a déjà ses propres groupes : elle ne peut pas devenir un groupe.');
            abort_if($class->ownInstallments()->exists(), 422, 'Cette classe a ses propres tranches : supprimez-les avant d’en faire un groupe (elle reprendra celles de la classe principale).');
        }

        return [...$data, 'parent_class_id' => $parent->id, 'cycle_id' => $parent->cycle_id, 'tuition_amount' => $parent->tuition_amount];
    }

    /**
     * Rangée d'office à sa place probable : un groupe juste après sa classe
     * principale et ses autres groupes, sinon d'après le libellé (« CM1 »
     * avant « CM2 »). Le directeur peut toujours la déplacer ensuite.
     */
    private function placeNewClass(SchoolClass $class): void
    {
        $others = SchoolClass::with('cycle')->ordered()->get()->where('id', '!=', $class->id)->values();

        if ($class->parent_class_id) {
            $family = $others->filter(fn ($c) => $c->id === $class->parent_class_id || $c->parent_class_id === $class->parent_class_id);
            $lastId = $family->last()->id;
            $position = $others->search(fn ($c) => $c->id === $lastId) + 1;
        } else {
            $key = fn ($c) => [(int) $c->cycle?->sort_order, PedagogicalOrder::rank($c->label)];
            $class->load('cycle');
            $position = $others->search(fn ($c) => $key($c) > $key($class));
            $position = $position === false ? $others->count() : $position;
        }

        $ids = $others->pluck('id')->all();
        array_splice($ids, $position, 0, [$class->id]);
        $this->applyOrder($ids);
    }

    public function cycles()
    {
        return SchoolCycle::orderBy('sort_order')->get();
    }

    private function applyOrder(array $ids): void
    {
        foreach (array_values($ids) as $position => $id) {
            SchoolClass::whereKey($id)->update(['sort_order' => $position + 1]);
        }
    }
}
