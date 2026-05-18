<?php

namespace Database\Seeders;

use App\Models\Security\Permission;
use App\Models\Security\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::withoutEvents(function () {
            User::updateOrCreate(
                ['id' => 0],
                [
                    'uuid'         => '00000000-0000-0000-0000-000000000000',
                    'name'         => 'System',
                    'email'        => 'system@example.com',
                    'password'     => Hash::make(Str::random(64)),
                    'active'       => false,
                    'job_position' => 'System User',
                    'created_by'   => 0,
                    'updated_by'   => 0,
                ]
            );
        });

        // Admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'         => 'System Admin',
                'email'        => 'admin@example.com',
                'password'     => Hash::make('password'),
                'active'       => true,
                'job_position' => 'System Administrator',
            ]
        );

        $adminRole = Role::where('key', 'admin')->first();
        if ($adminRole) {
            // Assign ALL permissions to admin role
            $allPermissions = Permission::pluck('id');
            $adminRole->permissions()->sync($allPermissions);

            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        }

        // Basic user
        $basicUser = User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name'         => 'Basic User',
                'email'        => 'user@example.com',
                'password'     => Hash::make('password'),
                'active'       => true,
                'job_position' => 'Staff',
            ]
        );

        $basicRole = Role::where('key', 'basic_user')->first();
        if ($basicRole) {
            $basicUser->roles()->syncWithoutDetaching([$basicRole->id]);
        }
    }
}
