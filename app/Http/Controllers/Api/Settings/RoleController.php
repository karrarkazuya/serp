<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreRoleRequest;
use App\Http\Requests\Settings\UpdateRoleRequest;
use App\Models\Security\Permission;
use App\Models\Security\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function read(Request $request): JsonResponse
    {
        $roles = Role::withCount(['permissions', 'users'])->orderBy('name')->paginate(20);
        return response()->json($roles);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json($role->load('permissions'));
    }

    public function create(StoreRoleRequest $request): JsonResponse
    {
        $role = DB::transaction(function () use ($request) {
            $role = Role::create([
                'name'        => $request->name,
                'key'         => $request->key,
                'description' => $request->description,
                'active'      => $request->boolean('active', true),
            ]);
            if ($request->filled('permissions')) {
                $role->permissions()->sync($request->permissions);
            }
            return $role;
        });

        return response()->json(['message' => 'Role created.', 'data' => $role->load('permissions')], 201);
    }

    public function write(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        DB::transaction(function () use ($request, $role) {
            $role->update(array_filter([
                'name'        => $request->name,
                'key'         => $request->key,
                'description' => $request->description,
                'active'      => $request->has('active') ? $request->boolean('active') : null,
            ], fn ($v) => $v !== null));

            if ($request->has('permissions')) {
                $role->permissions()->sync($request->permissions ?? []);
            }
        });

        return response()->json(['message' => 'Role updated.', 'data' => $role->fresh('permissions')]);
    }

    public function unlink(Role $role): JsonResponse
    {
        if (in_array($role->key, ['admin'])) {
            return response()->json(['message' => 'System roles cannot be deleted.'], 422);
        }

        DB::transaction(fn () => $role->delete());

        return response()->json(['message' => 'Role deleted.']);
    }
}
