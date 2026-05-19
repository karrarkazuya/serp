<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show(): View
    {
        $user = auth()->user()->load(['roles', 'defaultCompany', 'companies']);

        return view('profile.show', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $canEditEmail = $user->hasPermission('users.write') || $user->hasPermission('users.create');

        $rules = [
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
        ];

        if ($canEditEmail) {
            $rules['email'] = 'required|email|max:255|unique:users,email,' . $user->id;
        }

        $data = $request->validate($rules);

        if (! $canEditEmail) {
            unset($data['email']);
        }

        DB::transaction(fn () => $user->update($data));

        return back()->with('profile_success', 'Profile updated.');
    }

    public function updateLanguage(Request $request): RedirectResponse
    {
        $request->validate([
            'language' => 'required|in:en,ar',
        ]);

        DB::transaction(fn () => auth()->user()->update(['language' => $request->language]));

        return back()->with('language_success', __('profile.language_saved'));
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        DB::transaction(fn () => auth()->user()->update([
            'password' => Hash::make($request->password),
        ]));

        return back()->with('password_success', 'Password changed successfully.');
    }
}
