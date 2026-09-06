<?php

namespace Modules\Students\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('students.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['sometimes', 'exists:classes,id'],
            'guardian_id' => ['sometimes', 'exists:guardians,id'],
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'string', 'max:120'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:F,M'],
            'status' => ['sometimes', 'in:active,transferred,graduated,archived'],
        ];
    }
}
