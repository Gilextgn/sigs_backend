<?php

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('users.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role_id' => ['required', Rule::exists('roles', 'id')->where(fn ($query) => $query->where('code', '!=', 'platform_owner'))],
            'status' => ['nullable', 'in:active,inactive,locked'],
            // permissions individuelles cochées en plus du rôle (checkbox UI)
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'code')->where(fn ($query) => $query->where('code', 'not like', 'platform.%'))],
        ];
    }
}
