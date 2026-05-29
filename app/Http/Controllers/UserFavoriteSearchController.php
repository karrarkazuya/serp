<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserFavoriteSearchRequest;
use App\Models\UserFavoriteSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class UserFavoriteSearchController extends Controller
{
    public function store(StoreUserFavoriteSearchRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        DB::transaction(fn () => UserFavoriteSearch::create($data));

        return back()->with('success', __('common.favorite_saved'));
    }

    public function destroy(UserFavoriteSearch $favoriteSearch): RedirectResponse
    {
        abort_unless($favoriteSearch->user_id === auth()->id(), 403);

        DB::transaction(fn () => $favoriteSearch->delete());

        return back()->with('success', __('common.favorite_deleted'));
    }
}
