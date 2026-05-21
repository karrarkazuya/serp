<?php

namespace App\Services\Settings;

use App\Models\Security\Role;

class RoleService
{
    public function create(array $data, array $permissionIds = []): Role
    {
        $role = Role::create([
            'name'        => $data['name'],
            'key'         => $data['key'],
            'description' => $data['description'] ?? null,
            'active'      => $data['active'] ?? true,
        ]);

        if (!empty($permissionIds)) {
            $role->permissions()->sync($permissionIds);
        }

        return $role;
    }

    public function update(Role $role, array $data, ?array $permissionIds = null): Role
    {
        $role->update([
            'name'        => $data['name'] ?? $role->name,
            'key'         => $data['key'] ?? $role->key,
            'description' => $data['description'] ?? null,
            'active'      => $data['active'] ?? $role->active,
        ]);

        if ($permissionIds !== null) {
            $role->permissions()->sync($permissionIds);
        }

        return $role->fresh();
    }

    public function delete(Role $role): void
    {
        $role->permissions()->detach();
        $role->users()->detach();
        $role->delete();
    }
}
