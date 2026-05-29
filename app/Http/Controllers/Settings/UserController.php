<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserRequest;
use App\Http\Requests\Settings\UpdateUserRequest;
use App\Models\User;
use App\Services\Settings\UserService;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::with('roles')->whereKeyNot(0);

        SearchFilters::apply($query, $request);

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(User::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with('roles')->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('settings.users.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);

        $users = $query->paginate(20)->withQueryString();

        return view('settings.users.index', compact('users'));
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);
        $user->load('roles');
        return view('settings.users.show', compact('user'));
    }

    public function create()
    {
        $this->authorize('create', User::class);
        return view('settings.users.create');
    }

    public function store(StoreUserRequest $request)
    {
        $roleIds = $request->user()->hasPermission('users.assign_roles')
            ? $request->input('roles', [])
            : [];

        DB::transaction(fn () => $this->userService->create($request->validated(), $roleIds));

        return redirect()
            ->route('settings.users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $this->authorize('update', $user);
        $user->load('roles');
        return view('settings.users.edit', compact('user'));
    }

    public function write(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        // Guard: users cannot disable themselves and lock themselves out
        if ($user->is($request->user()) && array_key_exists('active', $data) && !$data['active']) {
            return back()->with('error', 'You cannot disable your own account.');
        }

        // Role assignment is a separate, more dangerous permission than users.write
        $roleIds = null;
        if ($request->has('roles') && $request->user()->hasPermission('users.assign_roles')) {
            $roleIds = $request->input('roles', []);
        }

        DB::transaction(fn () => $this->userService->update($user, $data, $roleIds));

        return redirect()
            ->route('settings.users.show', $user)
            ->with('success', 'User updated successfully.');
    }

    public function bulkUnlink(Request $request): RedirectResponse
    {
        $this->authorize('delete', User::class);

        $selectAll = $request->boolean('select_all');
        $ids = $request->input('ids', []);
        $actorId = auth()->id();

        DB::transaction(function () use ($selectAll, $ids, $actorId) {
            $query = User::whereKeyNot(0);
            if (!$selectAll) {
                $query->whereIn('id', $ids);
            }
            foreach ($query->get() as $user) {
                if ($user->id === $actorId) {
                    continue; // cannot delete self
                }
                if (Gate::allows('delete', $user)) {
                    $this->userService->delete($user);
                }
            }
        });

        return redirect()->route('settings.users.index')->with('success', 'Selected users deleted.');
    }

    public function unlink(User $user)
    {
        $this->authorize('delete', $user);

        if ($user->is(auth()->user())) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        DB::transaction(fn () => $this->userService->delete($user));

        return redirect()
            ->route('settings.users.index')
            ->with('success', 'User deleted.');
    }
}
