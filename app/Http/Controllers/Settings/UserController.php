<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserRequest;
use App\Http\Requests\Settings\UpdateUserRequest;
use App\Models\Security\Role;
use App\Models\User;
use App\Services\Chatter\ChatterService;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(private readonly ChatterService $chatterService) {}

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
        $roles = Role::where('active', true)->get();
        return view('settings.users.show', compact('user', 'roles'));
    }

    public function create()
    {
        $this->authorize('create', User::class);
        $roles = Role::where('active', true)->get();
        return view('settings.users.create', compact('roles'));
    }

    public function store(StoreUserRequest $request)
    {
        DB::transaction(function () use ($request, &$user) {
            $user = User::create([
                'name'         => $request->name,
                'email'        => $request->email,
                'password'     => Hash::make($request->password),
                'job_position' => $request->job_position,
                'phone'        => $request->phone,
                'active'       => $request->boolean('active', true),
            ]);

            if ($request->filled('roles')) {
                $user->roles()->sync($request->roles);
            }
        });

        return redirect()
            ->route('settings.users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $this->authorize('update', $user);
        $user->load('roles');
        $roles = Role::where('active', true)->get();
        return view('settings.users.edit', compact('user', 'roles'));
    }

    public function write(UpdateUserRequest $request, User $user)
    {
        DB::transaction(function () use ($request, $user) {
            $data = [
                'name'         => $request->name ?? $user->name,
                'email'        => $request->email ?? $user->email,
                'job_position' => $request->job_position,
                'phone'        => $request->phone,
                'active'       => $request->boolean('active', $user->active),
            ];

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);

            if ($request->has('roles')) {
                $user->roles()->sync($request->roles ?? []);
            }
        });

        return redirect()
            ->route('settings.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function unlink(User $user)
    {
        $this->authorize('delete', $user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        DB::transaction(fn () => $user->delete());

        return redirect()
            ->route('settings.users.index')
            ->with('success', 'User deleted.');
    }
}
