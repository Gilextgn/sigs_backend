<?php

namespace Modules\SchoolClasses\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('classes.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'cycle_id' => ['required', 'exists:school_cycles,id'],
            'code' => ['required', 'string', 'max:50', 'unique:classes,code'],
            'label' => ['required', 'string', 'max:120'],
            'tuition_amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
        ];
    }
}
