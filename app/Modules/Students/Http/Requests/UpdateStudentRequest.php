<?php

namespace Modules\Students\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('students.update') ?? false;
    }

    /** Numéros saisis « 01 66 18 98 77 » : on ne garde que les chiffres (10 attendus). */
    protected function prepareForValidation(): void
    {
        $guardian = $this->input('guardian');
        if (! is_array($guardian)) {
            return;
        }
        foreach (['phone', 'whatsapp'] as $field) {
            if (isset($guardian[$field]) && is_string($guardian[$field])) {
                $guardian[$field] = preg_replace('/\D/', '', $guardian[$field]) ?: null;
            }
        }
        $this->merge(['guardian' => $guardian]);
    }

    public function rules(): array
    {
        return [
            'class_id' => ['sometimes', \App\Support\SchoolRule::exists('classes')],
            'guardian_id' => ['sometimes', \App\Support\SchoolRule::exists('guardians')],
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'string', 'max:120'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:F,M'],
            'status' => ['sometimes', 'in:active,transferred,graduated,archived'],
            // Coordonnées du tuteur (partagées par ses autres enfants inscrits).
            'guardian' => ['sometimes', 'array'],
            'guardian.phone' => ['sometimes', 'digits:10'],
            'guardian.email' => ['nullable', 'email', 'max:180'],
            'guardian.whatsapp' => ['nullable', 'digits:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'guardian.phone.digits' => 'Le téléphone du tuteur doit comporter exactement 10 chiffres.',
            'guardian.whatsapp.digits' => 'Le numéro WhatsApp doit comporter exactement 10 chiffres.',
        ];
    }
}