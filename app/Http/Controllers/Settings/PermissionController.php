<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Models\Security\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function read(Request $request)
    {
        $this->authorize('viewAny', Permission::class);

        $sort = SortsTable::resolve(Permission::class, $request);

        $query = Permission::query();

        SearchFilters::apply($query, $request);

        $permissions = $query->orderBy('module')
            ->orderBy($sort['column'], $sort['direction'])
            ->get()
            ->groupBy('module');

        return view('settings.permissions.index', compact('permissions'));
    }
}
