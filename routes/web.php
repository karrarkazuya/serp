<?php

use App\Http\Controllers\Api\Chatter\ChatterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Chatter\ChatterFileController;
use App\Http\Controllers\Components\RelationLookupController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SharedLinkController;
use App\Http\Controllers\UserFavoriteSearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
// Public shared-link route — no authentication required. Throttled to
// frustrate token-enumeration scans (the token is the only secret).
Route::get('/share/{token}', [SharedLinkController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('share.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post')->middleware('throttle:10,1');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
| Shared/cross-module routes are defined inline here. Each feature module
| lives in its own file under routes/modules/ and is `require`d below so
| its routes inherit the `auth` middleware group.
*/
Route::middleware('auth')->group(function () {

    // ── Shared infrastructure ──────────────────────────────────────────

    // Unified file serving — UUID-based, permission-aware
    Route::get('/files/{uuid}', [FileController::class, 'serve'])->name('files.serve');
    Route::get('/files/{uuid}/thumbnail', [FileController::class, 'thumbnail'])->name('files.thumbnail');

    // Generic export — permission checked dynamically inside the controller.
    // Throttled because exports run heavy queries + build files; cap per-user
    // to 20/min so one user can't saturate workers.
    Route::post('/export', [ExportController::class, 'export'])
        ->middleware('throttle:20,1')
        ->name('export');

    // Generic import — permission is enforced dynamically inside the
    // controller (one per importable model in config/importable.php). The
    // whole row-by-row insert is wrapped in a single DB::transaction so a
    // single failed row rolls back every previously-imported row in the
    // batch. Tight throttle: imports run row-validating store() flow per
    // record, much heavier than exports.
    Route::get('/import/{modelKey}/template', [ImportController::class, 'template'])
        ->middleware('throttle:30,1')
        ->name('import.template');
    Route::post('/import', [ImportController::class, 'import'])
        ->middleware('throttle:10,1')
        ->name('import');

    // Personal saved searches — scoped per-user. The Search component's
    // "Save current search" persists the current URL query string against
    // the model class so the user can recall it on the same index later.
    Route::post('/favorite-searches', [UserFavoriteSearchController::class, 'store'])
        ->name('favorite-searches.store');
    Route::delete('/favorite-searches/{favoriteSearch}', [UserFavoriteSearchController::class, 'destroy'])
        ->name('favorite-searches.delete');

    // Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/count', [NotificationController::class, 'unreadCount'])->name('count');
        Route::get('/recent', [NotificationController::class, 'recent'])->name('recent');
        Route::post('/{notification}/seen', [NotificationController::class, 'markSeen'])->name('seen');
        Route::post('/seen-all', [NotificationController::class, 'markAllSeen'])->name('seen-all');
    });

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/language', [ProfileController::class, 'updateLanguage'])->name('profile.language');
    Route::put('/profile/password', [ProfileController::class, 'changePassword'])->name('profile.password');

    // Company context switcher (navbar POST)
    Route::post('/company/switch', [CompanySwitchController::class, 'switch'])->name('company.switch');

    // Generic relation-dropdown lookup (used by <x-relation-dropdown>)
    Route::get('/relation-dropdown/{table}', RelationLookupController::class)->name('relation-dropdown.lookup');

    // Chatter API (used by <x-chatter> component via fetch — must be web/session auth)
    Route::prefix('chatter')->name('api.chatter.')->group(function () {
        Route::get('/', [ChatterController::class, 'index'])->name('index');
        Route::post('/', [ChatterController::class, 'store'])->name('store');
        Route::get('/{chatterMessage}/file/{index}/{side}', [ChatterFileController::class, 'serve'])->name('file');
    });

    // ── Feature modules ────────────────────────────────────────────────
    // Each file defines its own prefix/name group and inherits `auth` from
    // this outer group. Keep this list alphabetical; add new modules here.

    require __DIR__.'/modules/chat.php';
    require __DIR__.'/modules/contacts.php';
    require __DIR__.'/modules/employees.php';
    require __DIR__.'/modules/workflow.php';
    require __DIR__.'/modules/accounting.php';
    require __DIR__.'/modules/inventory.php';
    require __DIR__.'/modules/settings.php';
});
