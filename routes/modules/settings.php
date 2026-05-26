<?php

use App\Http\Controllers\Settings\CompanyController;
use App\Http\Controllers\Settings\PermissionController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Settings module
|--------------------------------------------------------------------------
| Required from routes/web.php inside the auth middleware group.
*/
Route::prefix('settings')->name('settings.')->group(function () {

    // General settings
    Route::get('/', [SettingsController::class, 'index'])->middleware('permission:settings.read')->name('index');
    Route::put('/', [SettingsController::class, 'write'])->middleware('permission:settings.write')->name('update');
    Route::get('/system', [SettingsController::class, 'system'])->middleware('permission:settings.read')->name('system');

    // Companies
    Route::prefix('companies')->name('companies.')->group(function () {
        Route::get('/', [CompanyController::class, 'read'])->middleware('permission:companies.read')->name('index');
        Route::get('/create', [CompanyController::class, 'create'])->middleware('permission:companies.create')->name('create');
        Route::post('/', [CompanyController::class, 'store'])->middleware('permission:companies.create')->name('store');
        Route::get('/{company}', [CompanyController::class, 'show'])->middleware('permission:companies.read')->name('show');
        Route::get('/{company}/edit', [CompanyController::class, 'edit'])->middleware('permission:companies.write')->name('edit');
        Route::put('/{company}', [CompanyController::class, 'write'])->middleware('permission:companies.write')->name('update');
        Route::delete('/{company}', [CompanyController::class, 'unlink'])->middleware('permission:companies.unlink')->name('delete');
        Route::patch('/{company}/archive', [CompanyController::class, 'archive'])->middleware('permission:companies.write')->name('archive');
        Route::patch('/{company}/unarchive', [CompanyController::class, 'unarchive'])->middleware('permission:companies.write')->name('unarchive');
        Route::post('/{company}/comment', [CompanyController::class, 'addComment'])->middleware('permission:companies.write')->name('comment');
        Route::post('/{company}/users', [CompanyController::class, 'syncUsers'])->middleware('permission:companies.write')->name('sync-users');
    });

    // Users
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'read'])->middleware('permission:users.read')->name('index');
        Route::get('/create', [UserController::class, 'create'])->middleware('permission:users.create')->name('create');
        Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create')->name('store');
        Route::get('/{user}', [UserController::class, 'show'])->middleware('permission:users.read')->name('show');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.write')->name('edit');
        Route::put('/{user}', [UserController::class, 'write'])->middleware('permission:users.write')->name('update');
        Route::delete('/{user}', [UserController::class, 'unlink'])->middleware('permission:users.unlink')->name('delete');
    });

    // Roles
    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RoleController::class, 'read'])->middleware('permission:roles.read')->name('index');
        Route::get('/create', [RoleController::class, 'create'])->middleware('permission:roles.create')->name('create');
        Route::post('/', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('store');
        Route::get('/{role}', [RoleController::class, 'show'])->middleware('permission:roles.read')->name('show');
        Route::get('/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.write')->name('edit');
        Route::put('/{role}', [RoleController::class, 'write'])->middleware('permission:roles.write')->name('update');
        Route::delete('/{role}', [RoleController::class, 'unlink'])->middleware('permission:roles.unlink')->name('delete');
    });

    // Permissions
    Route::get('/permissions', [PermissionController::class, 'read'])->middleware('permission:roles.read')->name('permissions.index');
});
