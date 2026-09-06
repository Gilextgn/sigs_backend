<?php

namespace Modules\Students\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('students.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required', 'exists:classes,id'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:F,M'],

            // Le tuteur est obligatoire à l'inscription (règle métier existante).
            // Soit on référence un tuteur existant, soit on en crée un nouveau inline.
            'guardian_id' => ['required_without:guardian', 'nullable', 'exists:guardians,id'],
            'guardian' => ['required_without:guardian_id', 'nullable', 'array'],
            'guardian.full_name' => ['required_with:guardian', 'string', 'max:180'],
            'guardian.relationship_label' => ['required_with:guardian', 'string', 'max:50'],
            'guardian.phone' => ['required_with:guardian', 'string', 'max:40'],
            'guardian.address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
