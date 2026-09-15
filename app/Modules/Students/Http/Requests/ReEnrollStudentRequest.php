<?php

namespace Modules\Students\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReEnrollStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('students.reenroll') ?? false;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required', 'exists:classes,id'],
        ];
    }
}
