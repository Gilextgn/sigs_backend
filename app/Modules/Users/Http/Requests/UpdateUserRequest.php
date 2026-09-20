<?php

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('users.manage') ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id ?? null;

        return [
            'full_name' => ['sometimes', 'string', 'max:180'],
            'email' => ['sometimes', 'email', 'max:180', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role_id' => ['sometimes', Rule::exists('roles', 'id')->where(fn ($query) => $query->where('code', '!=', 'platform_owner'))],
            'status' => ['sometimes', 'in:active,inactive,locked'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'code')->where(fn ($query) => $query->where('code', 'not like', 'platform.%'))],
        ];
    }
}
