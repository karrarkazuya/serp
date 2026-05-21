<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreRoleRequest;
use App\Http\Requests\Settings\UpdateRoleRequest;
use App\Models\Security\Permission;
use App\Models\Security\Role;
use App\Services\Settings\RoleService;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roleService) {}
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
        DB::transaction(fn () => $this->roleService->create(
            $request->validated(),
            $request->input('permissions', [])
        ));

        return redirect()
            ->route('settings.roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function show(Role $role)
    {
        $this->authorize('view', $role);

        $role->load('permissions');

        return view('settings.roles.show', compact('role'));
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
        DB::transaction(fn () => $this->roleService->update(
            $role,
            $request->validated(),
            $request->has('permissions') ? $request->input('permissions', []) : null
        ));

        return redirect()
            ->route('settings.roles.show', $role)
            ->with('success', 'Role updated successfully.');
    }

    public function unlink(Role $role)
    {
        $this->authorize('delete', $role);

        if ($role->key === 'admin') {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        DB::transaction(fn () => $this->roleService->delete($role));

        return redirect()
            ->route('settings.roles.index')
            ->with('success', 'Role deleted.');
    }
}
