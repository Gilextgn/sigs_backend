<?php

namespace Modules\Tranches\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrancheRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('tranches.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required', 'exists:classes,id'],
            'label' => ['required', 'string', 'max:120'], // champ tranche obligatoire (select côté UI)
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
