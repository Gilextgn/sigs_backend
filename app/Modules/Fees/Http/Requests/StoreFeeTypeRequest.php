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
            'code' => ['nullable', 'string', 'max:50', 'unique:fee_types,code'],
            'label' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'is_active' => ['boolean'],
            'is_mandatory' => ['boolean'],
            // affectation à plusieurs classes en un clic
            'class_ids' => ['array'],
            'class_ids.*' => ['exists:classes,id'],
        ];
    }
}
