<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserRequest;
use App\Http\Requests\Settings\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function read(Request $request): JsonResponse
    {
        $query = User::with('roles')->whereKeyNot(0);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return response()->json($query->orderBy('name')->paginate(20));
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user->load('roles'));
    }

    public function create(StoreUserRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
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
            return $user;
        });

        return response()->json(['message' => 'User created.', 'data' => $user->load('roles')], 201);
    }

    public function write(UpdateUserRequest $request, User $user): JsonResponse
    {
        DB::transaction(function () use ($request, $user) {
            $data = array_filter([
                'name'         => $request->name,
                'email'        => $request->email,
                'job_position' => $request->job_position,
                'phone'        => $request->phone,
                'active'       => $request->has('active') ? $request->boolean('active') : null,
            ], fn ($v) => $v !== null);

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);

            if ($request->has('roles')) {
                $user->roles()->sync($request->roles ?? []);
            }
        });

        return response()->json(['message' => 'User updated.', 'data' => $user->fresh('roles')]);
    }

    public function unlink(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Cannot delete yourself.'], 422);
        }

        DB::transaction(fn () => $user->delete());

        return response()->json(['message' => 'User deleted.']);
    }
}
