<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreRoleRequest;
use App\Http\Requests\Settings\UpdateRoleRequest;
use App\Models\Security\Permission;
use App\Models\Security\Role;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function read(Request $request)
    {
        $this->authorize('viewAny', Role::class);

        $query = Role::withCount(['permissions', 'users']);

        SearchFilters::apply($query, $request);

        SortsTable::apply($query, $request);

        $roles = $query->paginate(20)->withQueryString();

        return view('settings.roles.index', compact('roles'));
    }

    public function create()
    {
        $this->authorize('create', Role::class);

        $permissions = Permission::orderBy('module')->orderBy('name')->get()->groupBy('module');

        return view('settings.roles.create', compact('permissions'));
    }

    public function store(StoreRoleRequest $request)
    {
        DB::transaction(function () use ($request, &$role) {
            $role = Role::create([
                'name'        => $request->name,
                'key'         => $request->key,
                'description' => $request->description,
                'active'      => $request->boolean('active', true),
            ]);

            if ($request->filled('permissions')) {
                $role->permissions()->sync($request->permissions);
            }
        });

        return redirect()
            ->route('settings.roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function edit(Role $role)
    {
        $this->authorize('update', $role);

        $role->load('permissions');
        $permissions = Permission::orderBy('module')->orderBy('name')->get()->groupBy('module');
        $assignedIds = $role->permissions->pluck('id')->toArray();

        return view('settings.roles.edit', compact('role', 'permissions', 'assignedIds'));
    }

    public function write(UpdateRoleRequest $request, Role $role)
    {
        DB::transaction(function () use ($request, $role) {
            $role->update([
                'name'        => $request->name ?? $role->name,
                'key'         => $request->key ?? $role->key,
                'description' => $request->description,
                'active'      => $request->boolean('active', $role->active),
            ]);

            if ($request->has('permissions')) {
                $role->permissions()->sync($request->permissions ?? []);
            }
        });

        return redirect()
            ->route('settings.roles.index')
            ->with('success', 'Role updated successfully.');
    }

    public function unlink(Role $role)
    {
        $this->authorize('delete', $role);

        if (in_array($role->key, ['admin'])) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        DB::transaction(fn () => $role->delete());

        return redirect()
            ->route('settings.roles.index')
            ->with('success', 'Role deleted.');
    }
}
