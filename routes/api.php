<?php

use App\Http\Controllers\Api\Chatter\ChatterController;
use App\Http\Controllers\Api\Contacts\ContactController;
use App\Http\Controllers\Api\Settings\CompanyController;
use App\Http\Controllers\Api\Settings\RoleController;
use App\Http\Controllers\Api\Settings\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Token auth (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', fn (Request $request) => $request->user());

    /*
    |--------------------------------------------------------------------------
    | Companies API
    |--------------------------------------------------------------------------
    */
    Route::prefix('companies')->name('api.companies.')->group(function () {
        Route::get('/', [CompanyController::class, 'read'])
            ->middleware('permission:companies.read')
            ->name('index');

        Route::post('/', [CompanyController::class, 'create'])
            ->middleware('permission:companies.create')
            ->name('create');

        Route::get('/{company}', [CompanyController::class, 'show'])
            ->middleware('permission:companies.read')
            ->name('show');

        Route::put('/{company}', [CompanyController::class, 'write'])
            ->middleware('permission:companies.write')
            ->name('update');

        Route::delete('/{company}', [CompanyController::class, 'unlink'])
            ->middleware('permission:companies.unlink')
            ->name('delete');
    });

    /*
    |--------------------------------------------------------------------------
    | Contacts API
    |--------------------------------------------------------------------------
    */
    Route::prefix('contacts')->name('api.contacts.')->group(function () {
        Route::get('/', [ContactController::class, 'read'])
            ->middleware('permission:contacts.read')
            ->name('index');

        Route::post('/', [ContactController::class, 'create'])
            ->middleware('permission:contacts.create')
            ->name('create');

        Route::get('/{contact}', [ContactController::class, 'show'])
            ->middleware('permission:contacts.read')
            ->name('show');

        Route::put('/{contact}', [ContactController::class, 'write'])
            ->middleware('permission:contacts.write')
            ->name('update');

        Route::delete('/{contact}', [ContactController::class, 'unlink'])
            ->middleware('permission:contacts.unlink')
            ->name('delete');

        Route::patch('/{contact}/archive', [ContactController::class, 'archive'])
            ->middleware('permission:contacts.write')
            ->name('archive');

        Route::get('/{contact}/chatter', [ContactController::class, 'chatter'])
            ->middleware('permission:contacts.read')
            ->name('chatter');
    });

    /*
    |--------------------------------------------------------------------------
    | Users API
    |--------------------------------------------------------------------------
    */
    Route::prefix('users')->name('api.users.')->group(function () {
        Route::get('/', [UserController::class, 'read'])
            ->middleware('permission:users.read')
            ->name('index');

        Route::post('/', [UserController::class, 'create'])
            ->middleware('permission:users.create')
            ->name('create');

        Route::get('/{user}', [UserController::class, 'show'])
            ->middleware('permission:users.read')
            ->name('show');

        Route::put('/{user}', [UserController::class, 'write'])
            ->middleware('permission:users.write')
            ->name('update');

        Route::delete('/{user}', [UserController::class, 'unlink'])
            ->middleware('permission:users.unlink')
            ->name('delete');
    });

    /*
    |--------------------------------------------------------------------------
    | Roles API
    |--------------------------------------------------------------------------
    */
    Route::prefix('roles')->name('api.roles.')->group(function () {
        Route::get('/', [RoleController::class, 'read'])
            ->middleware('permission:roles.read')
            ->name('index');

        Route::post('/', [RoleController::class, 'create'])
            ->middleware('permission:roles.create')
            ->name('create');

        Route::get('/{role}', [RoleController::class, 'show'])
            ->middleware('permission:roles.read')
            ->name('show');

        Route::put('/{role}', [RoleController::class, 'write'])
            ->middleware('permission:roles.write')
            ->name('update');

        Route::delete('/{role}', [RoleController::class, 'unlink'])
            ->middleware('permission:roles.unlink')
            ->name('delete');
    });

    /*
    |--------------------------------------------------------------------------
    | Chatter API
    |--------------------------------------------------------------------------
    */
    Route::prefix('chatter')->name('api.chatter.')->group(function () {
        Route::get('/', [ChatterController::class, 'index'])->name('index');
        Route::post('/', [ChatterController::class, 'store'])->name('store');
    });
});
