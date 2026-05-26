<?php

use App\Http\Controllers\Chat\ChatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Chat module
|--------------------------------------------------------------------------
| Required from routes/web.php inside the auth middleware group.
*/
Route::prefix('chat')->name('chat.')->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('index');
    Route::post('/rooms', [ChatController::class, 'createRoom'])->name('rooms.create');
    Route::post('/direct/{user}', [ChatController::class, 'openDirect'])->name('direct');
    Route::get('/{room}', [ChatController::class, 'show'])->name('show');
    Route::post('/{room}/messages', [ChatController::class, 'store'])->name('store');
    Route::get('/{room}/files/{file}', [ChatController::class, 'file'])->name('file');
    Route::post('/{room}/members', [ChatController::class, 'addMember'])->name('members.add');
    Route::delete('/{room}/members/{user}', [ChatController::class, 'removeMember'])->name('members.remove');
});
