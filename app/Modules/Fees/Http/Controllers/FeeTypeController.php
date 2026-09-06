<?php

namespace Modules\Fees\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Fees\Http\Requests\StoreFeeTypeRequest;
use Modules\Fees\Models\FeeType;

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
        $feeType = FeeType::create(collect($data)->except('class_ids')->all());
        $feeType->classes()->sync($data['class_ids'] ?? []);

        return $feeType->load('classes:id,label');
    }

    public function update(StoreFeeTypeRequest $request, FeeType $feeType)
    {
        $data = $request->validated();
        $feeType->update(collect($data)->except('class_ids')->all());

        if (array_key_exists('class_ids', $data)) {
            $feeType->classes()->sync($data['class_ids']);
        }

        return $feeType->fresh('classes:id,label');
    }

    public function destroy(FeeType $feeType)
    {
        $feeType->delete();

        return response()->noContent();
    }
}
