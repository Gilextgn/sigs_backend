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
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:120'],
            'pay_mode' => ['nullable', 'in:hourly,monthly'],
            // Salaire fixe : exigé seulement quand c'est le mode de paie retenu.
            // À l'heure, la paie vient des séances faites (voir PayrollController).
            'monthly_salary' => ['exclude_if:pay_mode,hourly', 'required_if:pay_mode,monthly', 'nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }

    /** À l'heure, aucun salaire mensuel n'est conservé : il induirait en erreur. */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        if (($data['pay_mode'] ?? 'hourly') === 'hourly') {
            $data['monthly_salary'] = null;
        }

        return $data;
    }
}
