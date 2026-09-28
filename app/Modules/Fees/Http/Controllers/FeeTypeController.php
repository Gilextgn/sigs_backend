<?php

namespace Modules\Fees\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Fees\Http\Requests\StoreFeeTypeRequest;
use Modules\Fees\Models\FeeType;
use Modules\SchoolClasses\Models\SchoolClass;

class FeeTypeController extends Controller
{
    public function index(Request $request)
    {
        return FeeType::with('classes:id,label')
            ->when($request->search, fn ($q, $s) => $q->where('label', 'like', "%{$s}%"))
            ->orderBy('label')
            ->get();
    }

    public function store(StoreFeeTypeRequest $request)
    {
        $data = $request->validated();
        $feeType = FeeType::create($this->attributes($data));
        $feeType->classes()->sync(SchoolClass::pricingIds($data['class_ids'] ?? []));

        return $feeType->load('classes:id,label');
    }

    public function update(StoreFeeTypeRequest $request, FeeType $feeType)
    {
        $data = $request->validated();
        $feeType->update($this->attributes($data, $feeType));

        if (array_key_exists('class_ids', $data)) {
            $feeType->classes()->sync(SchoolClass::pricingIds($data['class_ids']));
        }

        return $feeType->fresh('classes:id,label');
    }

    /** Un frais mensuel sans mois choisis est facturé de septembre à juin ; un frais unique n'a pas de mois. */
    private function attributes(array $data, ?FeeType $current = null): array
    {
        $attributes = collect($data)->except('class_ids')->all();
        $cycle = $attributes['billing_cycle'] ?? $current?->billing_cycle ?? 'once';

        if ($cycle === 'monthly') {
            $attributes['months'] = array_values(array_map('intval', $attributes['months'] ?? $current?->months ?? FeeType::DEFAULT_MONTHS));
        } else {
            $attributes['months'] = null;
        }

        return $attributes;
    }

    public function destroy(FeeType $feeType)
    {
        // Un frais déjà encaissé figure sur des reçus : le supprimer les casserait.
        $paid = \Modules\Payments\Models\PaymentItem::where('fee_type_id', $feeType->id)->count();
        abort_if($paid > 0, 422, "Impossible de supprimer « {$feeType->label} » : il a déjà été encaissé {$paid} fois et figure sur des reçus. Retirez-lui ses classes (ou passez-le « sur inscription » sans inscrit) pour qu'il ne soit plus demandé.");

        $feeType->delete();

        return response()->noContent();
    }
}
