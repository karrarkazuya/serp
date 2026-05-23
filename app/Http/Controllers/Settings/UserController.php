<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserRequest;
use App\Http\Requests\Settings\UpdateUserRequest;
use App\Models\User;
use App\Services\Settings\UserService;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::with('roles')->whereKeyNot(0);

        SearchFilters::apply($query, $request);

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
        DB::transaction(fn () => $this->userService->create(
            $request->validated(),
            $request->input('roles', [])
        ));

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
        DB::transaction(fn () => $this->userService->update(
            $user,
            $request->validated(),
            $request->has('roles') ? $request->input('roles', []) : null
        ));

        return redirect()
            ->route('settings.users.show', $user)
            ->with('success', 'User updated successfully.');
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
