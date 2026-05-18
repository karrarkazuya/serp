<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('roles.write');
    }

    public function rules(): array
    {
        $roleId = $this->route('role')->id ?? null;

        return [
            'name'          => "sometimes|required|string|max:100|unique:roles,name,{$roleId}",
            'key'           => "sometimes|required|string|max:100|unique:roles,key,{$roleId}|regex:/^[a-z0-9_]+$/",
            'description'   => 'nullable|string|max:255',
            'active'        => 'boolean',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ];
    }
}
