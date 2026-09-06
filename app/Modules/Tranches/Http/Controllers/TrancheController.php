<?php

namespace Modules\Tranches\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
