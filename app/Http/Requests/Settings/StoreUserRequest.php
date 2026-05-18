<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('users.create');
    }

    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|string|min:8|confirmed',
            'job_position' => 'nullable|string|max:100',
            'phone'        => 'nullable|string|max:50',
            'active'       => 'boolean',
            'roles'        => 'nullable|array',
            'roles.*'      => 'exists:roles,id',
        ];
    }
}
