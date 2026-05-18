<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('users.write');
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id ?? null;

        return [
            'name'         => 'sometimes|required|string|max:255',
            'email'        => "sometimes|required|email|unique:users,email,{$userId}",
            'password'     => 'nullable|string|min:8|confirmed',
            'job_position' => 'nullable|string|max:100',
            'phone'        => 'nullable|string|max:50',
            'active'       => 'boolean',
            'roles'        => 'nullable|array',
            'roles.*'      => 'exists:roles,id',
        ];
    }
}
