<?php

namespace Database\Seeders;

use App\Models\Security\Permission;
use App\Models\Security\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Admin role — gets ALL permissions in UserSeeder
        Role::updateOrCreate(
            ['key' => 'admin'],
            [
                'name'        => 'Administrator',
                'key'         => 'admin',
                'description' => 'Full access to all modules and settings.',
                'active'      => true,
            ]
        );

        // Basic user role — limited read access
        $basicUser = Role::updateOrCreate(
            ['key' => 'basic_user'],
            [
                'name'        => 'Basic User',
                'key'         => 'basic_user',
                'description' => 'Read-only access to contacts.',
                'active'      => true,
            ]
        );

        // Assign read-only permissions to basic user
        $readPermissions = Permission::whereIn('key', [
            'contacts.read',
        ])->pluck('id');

        $basicUser->permissions()->sync($readPermissions);
    }
}
