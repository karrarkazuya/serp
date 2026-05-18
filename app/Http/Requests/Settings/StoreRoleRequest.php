<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('roles.create');
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:100|unique:roles,name',
            'key'         => 'required|string|max:100|unique:roles,key|regex:/^[a-z0-9_]+$/',
            'description' => 'nullable|string|max:255',
            'active'      => 'boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ];
    }
}
