<?php

namespace Modules\Teachers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('teachers.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:180'],
            'phone' => ['nullable', 'digits:10'],
            'subject' => ['nullable', 'string', 'max:120'],
            'pay_mode' => ['nullable', 'in:hourly,monthly'],
            // Salaire fixe : exigé seulement quand c'est le mode de paie retenu.
            // À l'heure, la paie vient des séances faites (voir PayrollController).
            'monthly_salary' => ['exclude_if:pay_mode,hourly', 'required_if:pay_mode,monthly', 'nullable', 'numeric', 'min:0'],
            // À l'heure : le tarif est saisi sur la fiche (repris par ses affectations).
            'hourly_rate' => ['exclude_if:pay_mode,monthly', 'required_unless:pay_mode,monthly', 'nullable', 'numeric', 'min:1'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }

    /** Numéro saisi « 01 66 18 98 77 » : on ne garde que les chiffres. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->phone)) {
            $this->merge(['phone' => preg_replace('/\D/', '', $this->phone) ?: null]);
        }
    }

    /** À l'heure, aucun salaire mensuel n'est conservé : il induirait en erreur. */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        if (($data['pay_mode'] ?? 'hourly') === 'hourly') {
            $data['monthly_salary'] = null;
        } else {
            $data['hourly_rate'] = null;
        }

        return $data;
    }

    public function messages(): array
    {
        return [
            'phone.digits' => 'Le téléphone doit comporter exactement 10 chiffres.',
            'hourly_rate.required_unless' => 'Indiquez le tarif horaire de cet enseignant.',
        ];
    }
}
