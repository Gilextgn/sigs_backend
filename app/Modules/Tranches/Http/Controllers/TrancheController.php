<?php

namespace Modules\Tranches\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\SchoolClasses\Models\SchoolClass;
use Modules\Tranches\Http\Requests\StoreTrancheRequest;
use Modules\Tranches\Models\TuitionInstallment;

class TrancheController extends Controller
{
    public function index(Request $request)
    {
        return TuitionInstallment::with('schoolClass')
            ->when($request->class_id, fn ($q, $id) => $q->where('class_id', $id))
            ->orderBy('due_date')
            ->get();
    }

    public function store(StoreTrancheRequest $request)
    {
        $data = $request->validated();
        $this->assertWithinTuitionCeiling($data['class_id'], $data['amount']);

        return TuitionInstallment::create($data);
    }

    /**
     * Toutes les tranches d'une classe en une fois : le directeur découpe sa
     * scolarité d'un seul geste. Tout ou rien, et le plafond est vérifié sur
     * l'ensemble plutôt que ligne à ligne.
     */
    public function bulkStore(Request $request)
    {
        abort_unless($request->user()?->hasPermission('tranches.manage'), 403);

        $data = $request->validate([
            'class_id' => ['required', \App\Support\SchoolRule::exists('classes')],
            'tranches' => ['required', 'array', 'min:1', 'max:12'],
            'tranches.*.label' => ['required', 'string', 'max:120'],
            'tranches.*.amount' => ['required', 'numeric', 'min:0.01'],
            'tranches.*.due_date' => ['nullable', 'date'],
        ]);

        $class = SchoolClass::findOrFail($data['class_id']);
        $existingTotal = (float) TuitionInstallment::where('class_id', $class->id)->sum('amount');
        $added = array_sum(array_map(fn ($tranche) => (float) $tranche['amount'], $data['tranches']));

        if ($existingTotal + $added > (float) $class->tuition_amount) {
            $room = max((float) $class->tuition_amount - $existingTotal, 0);
            throw ValidationException::withMessages([
                'tranches' => 'Le total dépasse la scolarité de la classe : il reste '.number_format($room, 0, ',', ' ').' XOF à répartir.',
            ]);
        }

        $created = DB::transaction(fn () => collect($data['tranches'])->map(fn ($tranche) => TuitionInstallment::create([
            'class_id' => $class->id,
            'label' => $tranche['label'],
            'amount' => $tranche['amount'],
            'due_date' => $tranche['due_date'] ?? null,
        ]))->all());

        return response()->json($created, 201);
    }

    public function update(StoreTrancheRequest $request, TuitionInstallment $tranche)
    {
        $data = $request->validated();
        $this->assertWithinTuitionCeiling($data['class_id'], $data['amount'], excludeId: $tranche->id);

        $tranche->update($data);

        return $tranche->fresh();
    }

    public function destroy(TuitionInstallment $tranche)
    {
        $tranche->delete();

        return response()->noContent();
    }

    /**
     * Règle métier existante (trigger SQL trg_tuition_installments_before_insert/update) :
     * la somme des tranches d'une classe ne doit jamais dépasser sa scolarité annuelle.
     */
    private function assertWithinTuitionCeiling(int $classId, float $amount, ?int $excludeId = null): void
    {
        $class = SchoolClass::findOrFail($classId);

        $existingTotal = TuitionInstallment::where('class_id', $classId)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->sum('amount');

        if (($existingTotal + $amount) > $class->tuition_amount) {
            throw ValidationException::withMessages([
                'amount' => 'Le total des tranches ne doit jamais dépasser le montant de la scolarité ('.$class->tuition_amount.').',
            ]);
        }
    }
}
