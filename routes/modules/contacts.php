<?php

use App\Http\Controllers\Contacts\ContactController;
use App\Http\Controllers\Contacts\TagController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Contacts module
|--------------------------------------------------------------------------
| Required from routes/web.php inside the auth middleware group.
*/
Route::prefix('contacts')->name('contacts.')->group(function () {
    Route::get('/', [ContactController::class, 'read'])->middleware('permission:contacts.read')->name('index');
    Route::get('/create', [ContactController::class, 'create'])->middleware('permission:contacts.create')->name('create');
    Route::post('/', [ContactController::class, 'store'])->middleware('permission:contacts.create')->name('store');

    Route::prefix('configuration/tags')->name('tags.')->group(function () {
        Route::get('/', [TagController::class, 'read'])->middleware('permission:contacts.read')->name('index');
        Route::get('/create', [TagController::class, 'create'])->middleware('permission:contacts.write')->name('create');
        Route::post('/', [TagController::class, 'store'])->middleware('permission:contacts.write')->name('store');
        Route::get('/{tag}', [TagController::class, 'show'])->middleware('permission:contacts.read')->name('show');
        Route::get('/{tag}/edit', [TagController::class, 'edit'])->middleware('permission:contacts.write')->name('edit');
        Route::put('/{tag}', [TagController::class, 'write'])->middleware('permission:contacts.write')->name('update');
        Route::delete('/{tag}', [TagController::class, 'unlink'])->middleware('permission:contacts.unlink')->name('delete');
    });

    Route::delete('/bulk', [ContactController::class, 'bulkUnlink'])->middleware('permission:contacts.unlink')->name('bulk-delete');

    Route::get('/{contact}', [ContactController::class, 'show'])->middleware('permission:contacts.read')->name('show');
    Route::get('/{contact}/edit', [ContactController::class, 'edit'])->middleware('permission:contacts.write')->name('edit');
    Route::put('/{contact}', [ContactController::class, 'write'])->middleware('permission:contacts.write')->name('update');
    Route::delete('/{contact}', [ContactController::class, 'unlink'])->middleware('permission:contacts.unlink')->name('delete');
    Route::patch('/{contact}/archive', [ContactController::class, 'archive'])->middleware('permission:contacts.write')->name('archive');
    Route::patch('/{contact}/unarchive', [ContactController::class, 'unarchive'])->middleware('permission:contacts.write')->name('unarchive');
    Route::post('/{contact}/comment', [ContactController::class, 'addComment'])->middleware('permission:contacts.write')->name('comment');
});
