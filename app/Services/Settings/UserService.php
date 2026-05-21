<?php

namespace App\Services\Settings;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function create(array $data, array $roleIds = []): User
    {
        $user = User::create([
            'name'         => $data['name'],
            'email'        => $data['email'],
            'password'     => Hash::make($data['password']),
            'job_position' => $data['job_position'] ?? null,
            'phone'        => $data['phone'] ?? null,
            'active'       => $data['active'] ?? true,
        ]);

        if (!empty($roleIds)) {
            $user->roles()->sync($roleIds);
        }

        return $user;
    }

    public function update(User $user, array $data, ?array $roleIds = null): User
    {
        $payload = [
            'name'         => $data['name'] ?? $user->name,
            'email'        => $data['email'] ?? $user->email,
            'job_position' => $data['job_position'] ?? null,
            'phone'        => $data['phone'] ?? null,
            'active'       => $data['active'] ?? $user->active,
        ];

        if (!empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);

        if ($roleIds !== null) {
            $user->roles()->sync($roleIds);
        }

        return $user->fresh();
    }

    public function delete(User $user): void
    {
        $user->roles()->detach();
        $user->delete();
    }
}
