<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Contacts\ContactController;
use App\Http\Controllers\Contacts\TagController;
use App\Http\Controllers\Components\RelationLookupController;
use App\Http\Controllers\Settings\CompanyController;
use App\Http\Controllers\Settings\PermissionController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Company context switcher (navbar POST)
    Route::post('/company/switch', [CompanySwitchController::class, 'switch'])->name('company.switch');

    Route::get('/relation-dropdown/{table}', RelationLookupController::class)->name('relation-dropdown.lookup');

    /*
    |--------------------------------------------------------------------------
    | Contacts module
    |--------------------------------------------------------------------------
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

        Route::get('/{contact}', [ContactController::class, 'show'])->middleware('permission:contacts.read')->name('show');
        Route::get('/{contact}/edit', [ContactController::class, 'edit'])->middleware('permission:contacts.write')->name('edit');
        Route::put('/{contact}', [ContactController::class, 'write'])->middleware('permission:contacts.write')->name('update');
        Route::delete('/{contact}', [ContactController::class, 'unlink'])->middleware('permission:contacts.unlink')->name('delete');
        Route::patch('/{contact}/archive', [ContactController::class, 'archive'])->middleware('permission:contacts.write')->name('archive');
        Route::patch('/{contact}/unarchive', [ContactController::class, 'unarchive'])->middleware('permission:contacts.write')->name('unarchive');
        Route::post('/{contact}/comment', [ContactController::class, 'addComment'])->middleware('permission:contacts.write')->name('comment');
    });

    /*
    |--------------------------------------------------------------------------
    | Settings module
    |--------------------------------------------------------------------------
    */
    Route::prefix('settings')->name('settings.')->group(function () {

        // General settings
        Route::get('/', [SettingsController::class, 'index'])->middleware('permission:settings.read')->name('index');
        Route::put('/', [SettingsController::class, 'write'])->middleware('permission:settings.write')->name('update');

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
            Route::get('/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.write')->name('edit');
            Route::put('/{role}', [RoleController::class, 'write'])->middleware('permission:roles.write')->name('update');
            Route::delete('/{role}', [RoleController::class, 'unlink'])->middleware('permission:roles.unlink')->name('delete');
        });

        // Permissions
        Route::get('/permissions', [PermissionController::class, 'read'])->middleware('permission:roles.read')->name('permissions.index');
    });
});
