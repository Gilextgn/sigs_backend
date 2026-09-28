<?php

namespace Modules\Students\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('students.create') ?? false;
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
            'class_id' => ['required', \App\Support\SchoolRule::exists('classes')],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:F,M'],

            // Le tuteur est obligatoire à l'inscription (règle métier existante).
            // Soit on référence un tuteur existant, soit on en crée un nouveau inline.
            'guardian_id' => ['required_without:guardian', 'nullable', \App\Support\SchoolRule::exists('guardians')],
            'guardian' => ['required_without:guardian_id', 'nullable', 'array'],
            'guardian.full_name' => ['required_with:guardian', 'string', 'max:180'],
            'guardian.relationship_label' => ['required_with:guardian', 'string', 'max:50'],
            'guardian.phone' => ['required_with:guardian', 'digits:10'],
            'guardian.address' => ['nullable', 'string', 'max:255'],
            // Canaux d'envoi automatique du reçu.
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