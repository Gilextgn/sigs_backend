<?php

namespace Modules\Fees\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeeTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('fees.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:50', \App\Support\SchoolRule::unique('fee_types', 'code')->ignore($this->route('feeType'))],
            'label' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            // « monthly » : le montant est celui d'un mois, dû pour chaque mois listé.
            'billing_cycle' => ['sometimes', 'in:once,monthly'],
            'months' => ['nullable', 'array', 'min:1', 'max:12'],
            'months.*' => ['integer', 'between:1,12', 'distinct'],
            'is_active' => ['boolean'],
            'is_mandatory' => ['boolean'],
            // affectation à plusieurs classes en un clic
            'class_ids' => ['array'],
            'class_ids.*' => [\App\Support\SchoolRule::exists('classes')],
        ];
    }
}
