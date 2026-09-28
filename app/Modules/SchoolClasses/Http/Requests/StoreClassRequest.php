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
            'code' => ['required', 'string', 'max:50', \App\Support\SchoolRule::unique('classes', 'code')->ignore($this->route('class'))],
            'label' => ['required', 'string', 'max:120'],
            // Un groupe (CE1 B) reprend la scolarité de sa classe principale.
            'parent_class_id' => ['nullable', 'integer', \App\Support\SchoolRule::exists('classes')],
            'tuition_amount' => ['required_without:parent_class_id', 'nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'academic_year_id' => ['nullable', \App\Support\SchoolRule::exists('academic_years')],
        ];
    }
}
