<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SharedLinkController;
use App\Http\Controllers\Workflow\ShareController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Contacts\ContactController;
use App\Http\Controllers\Contacts\TagController;
use App\Http\Controllers\Components\RelationLookupController;
use App\Http\Controllers\Settings\CompanyController;
use App\Http\Controllers\Settings\PermissionController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\Workflow\DepartmentController;
use App\Http\Controllers\Workflow\GroupController;
use App\Http\Controllers\Workflow\ManagerController;
use App\Http\Controllers\Workflow\ProcedureController;
use App\Http\Controllers\Workflow\ProcedureTemplateController;
use App\Http\Controllers\Workflow\TicketController;
use App\Http\Controllers\Workflow\TicketTemplateController;
use App\Http\Controllers\Workflow\WorkflowDashboardController;
use App\Http\Controllers\Workflow\WorkflowReportController;
use App\Http\Controllers\Workflow\WorkflowSettingsController;
use App\Http\Controllers\Workflow\WorkflowUserController;
use App\Http\Controllers\Chat\ChatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
// Public shared-link route — no authentication required
Route::get('/share/{token}', [SharedLinkController::class, 'show'])->name('share.show');

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

    // Chat
    Route::prefix('chat')->name('chat.')->group(function () {
        Route::get('/', [ChatController::class, 'index'])->name('index');
        Route::post('/rooms', [ChatController::class, 'createRoom'])->name('rooms.create');
        Route::get('/{room}', [ChatController::class, 'show'])->name('show');
        Route::post('/{room}/messages', [ChatController::class, 'store'])->name('store');
        Route::get('/{room}/files/{file}', [ChatController::class, 'file'])->name('file');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/language', [ProfileController::class, 'updateLanguage'])->name('profile.language');
    Route::put('/profile/password', [ProfileController::class, 'changePassword'])->name('profile.password');

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
    | Workflow module
    |--------------------------------------------------------------------------
    */
    Route::prefix('workflow')->name('workflow.')->group(function () {

        // Dashboard
        Route::get('/', [WorkflowDashboardController::class, 'index'])->middleware('permission:workflow.tickets.read')->name('dashboard');

        // Tickets
        Route::prefix('tickets')->name('tickets.')->group(function () {
            Route::get('/', [TicketController::class, 'read'])->middleware('permission:workflow.tickets.read')->name('index');
            Route::get('/create', [TicketController::class, 'create'])->middleware('permission:workflow.tickets.create')->name('create');
            Route::post('/', [TicketController::class, 'store'])->middleware('permission:workflow.tickets.create')->name('store');
            Route::get('/{ticket}', [TicketController::class, 'show'])->middleware('permission:workflow.tickets.read')->name('show');
            Route::get('/{ticket}/edit', [TicketController::class, 'edit'])->middleware('permission:workflow.tickets.write')->name('edit');
            Route::put('/{ticket}', [TicketController::class, 'write'])->middleware('permission:workflow.tickets.write')->name('update');
            Route::delete('/{ticket}', [TicketController::class, 'unlink'])->middleware('permission:workflow.tickets.unlink')->name('delete');
            Route::patch('/{ticket}/resolve', [TicketController::class, 'resolve'])->middleware('permission:workflow.tickets.write')->name('resolve');
            Route::patch('/{ticket}/close', [TicketController::class, 'close'])->middleware('permission:workflow.tickets.write')->name('close');
            Route::patch('/{ticket}/reopen', [TicketController::class, 'reopen'])->middleware('permission:workflow.tickets.write')->name('reopen');
            Route::patch('/{ticket}/archive', [TicketController::class, 'archive'])->middleware('permission:workflow.tickets.write')->name('archive');
            Route::patch('/{ticket}/unarchive', [TicketController::class, 'unarchive'])->middleware('permission:workflow.tickets.write')->name('unarchive');
            Route::post('/{ticket}/comment', [TicketController::class, 'addComment'])->middleware('permission:workflow.tickets.write')->name('comment');
            Route::patch('/{ticket}/field', [TicketController::class, 'saveField'])->middleware('permission:workflow.tickets.write')->name('save-field');
            Route::patch('/{ticket}/inputs', [TicketController::class, 'saveInputs'])->middleware('permission:workflow.tickets.write')->name('save-inputs');
            Route::post('/{ticket}/viewers', [TicketController::class, 'addViewer'])->middleware('permission:workflow.tickets.write')->name('add-viewer');
            Route::post('/{ticket}/chat', [TicketController::class, 'sendChat'])->middleware('permission:workflow.tickets.write')->name('chat.store');
            Route::get('/{ticket}/chat/files/{file}', [TicketController::class, 'chatFile'])->middleware('permission:workflow.tickets.read')->name('chat.file');
            Route::delete('/{ticket}/viewers/{user}', [TicketController::class, 'removeViewer'])->middleware('permission:workflow.tickets.write')->name('remove-viewer');
            Route::get('/{ticket}/viewers/lookup', [TicketController::class, 'viewersLookup'])->middleware('permission:workflow.tickets.read')->name('viewers-lookup');
        });

        // Sharing
        Route::prefix('share')->name('share.')->group(function () {
            Route::post('/ticket/{ticket}/toggle', [ShareController::class, 'toggleTicket'])->middleware('permission:workflow.tickets.write')->name('ticket.toggle');
            Route::patch('/ticket/{ticket}/message', [ShareController::class, 'messageTicket'])->middleware('permission:workflow.tickets.write')->name('ticket.message');
            Route::post('/procedure/{procedure}/toggle', [ShareController::class, 'toggleProcedure'])->middleware('permission:workflow.procedures.write')->name('procedure.toggle');
            Route::patch('/procedure/{procedure}/message', [ShareController::class, 'messageProcedure'])->middleware('permission:workflow.procedures.write')->name('procedure.message');
            Route::post('/procedure/{procedure}/ticket/{ticket}/toggle', [ShareController::class, 'toggleProcedureTicket'])->middleware('permission:workflow.procedures.write')->name('procedure-ticket.toggle');
            Route::patch('/procedure/{procedure}/ticket/{ticket}/message', [ShareController::class, 'messageProcedureTicket'])->middleware('permission:workflow.procedures.write')->name('procedure-ticket.message');
        });

        // Procedures
        Route::prefix('procedures')->name('procedures.')->group(function () {
            Route::get('/', [ProcedureController::class, 'read'])->middleware('permission:workflow.procedures.read')->name('index');
            Route::get('/create', [ProcedureController::class, 'create'])->middleware('permission:workflow.procedures.create')->name('create');
            Route::post('/', [ProcedureController::class, 'store'])->middleware('permission:workflow.procedures.create')->name('store');
            Route::get('/{procedure}', [ProcedureController::class, 'show'])->middleware('permission:workflow.procedures.read')->name('show');
            Route::delete('/{procedure}', [ProcedureController::class, 'unlink'])->middleware('permission:workflow.procedures.unlink')->name('delete');
            Route::patch('/{procedure}/close', [ProcedureController::class, 'close'])->middleware('permission:workflow.procedures.write')->name('close');
            Route::patch('/{procedure}/archive', [ProcedureController::class, 'archive'])->middleware('permission:workflow.procedures.write')->name('archive');
            Route::patch('/{procedure}/unarchive', [ProcedureController::class, 'unarchive'])->middleware('permission:workflow.procedures.write')->name('unarchive');
            Route::post('/{procedure}/comment', [ProcedureController::class, 'addComment'])->middleware('permission:workflow.procedures.write')->name('comment');
            Route::patch('/{procedure}/tickets/{ticket}/inputs', [ProcedureController::class, 'saveTicketInputs'])->middleware('permission:workflow.procedures.write')->name('tickets.inputs');
            Route::patch('/{procedure}/tickets/{ticket}/complete', [ProcedureController::class, 'completeTicket'])->middleware('permission:workflow.procedures.write')->name('tickets.complete');
            Route::patch('/{procedure}/tickets/{ticket}/reject', [ProcedureController::class, 'rejectTicket'])->middleware('permission:workflow.procedures.write')->name('tickets.reject');
            Route::patch('/{procedure}/tickets/{ticket}/skip', [ProcedureController::class, 'skipTicket'])->middleware('permission:workflow.procedures.write')->name('tickets.skip');
            Route::post('/{procedure}/tickets/{ticket}/path', [ProcedureController::class, 'choosePath'])->middleware('permission:workflow.procedures.write')->name('tickets.path');
        });

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [WorkflowReportController::class, 'index'])->middleware('permission:workflow.tickets.read')->name('index');
            Route::get('/{report}', [WorkflowReportController::class, 'show'])->middleware('permission:workflow.tickets.read')->name('show');
        });

        // Settings
        Route::get('/settings', [WorkflowSettingsController::class, 'index'])->middleware('permission:workflow.config.read')->name('settings.index');

        // Configuration
        Route::prefix('configuration')->name('config.')->group(function () {

            Route::prefix('groups')->name('groups.')->group(function () {
                Route::get('/', [GroupController::class, 'read'])->middleware('permission:workflow.config.read')->name('index');
                Route::get('/create', [GroupController::class, 'create'])->middleware('permission:workflow.config.write')->name('create');
                Route::post('/', [GroupController::class, 'store'])->middleware('permission:workflow.config.write')->name('store');
                Route::get('/{group}', [GroupController::class, 'show'])->middleware('permission:workflow.config.read')->name('show');
                Route::get('/{group}/edit', [GroupController::class, 'edit'])->middleware('permission:workflow.config.write')->name('edit');
                Route::put('/{group}', [GroupController::class, 'write'])->middleware('permission:workflow.config.write')->name('update');
                Route::delete('/{group}', [GroupController::class, 'unlink'])->middleware('permission:workflow.config.unlink')->name('delete');
                Route::post('/{group}/comment', [GroupController::class, 'addComment'])->middleware('permission:workflow.config.write')->name('comment');
            });

            Route::prefix('departments')->name('departments.')->group(function () {
                Route::get('/', [DepartmentController::class, 'read'])->middleware('permission:workflow.config.read')->name('index');
                Route::get('/create', [DepartmentController::class, 'create'])->middleware('permission:workflow.config.write')->name('create');
                Route::post('/', [DepartmentController::class, 'store'])->middleware('permission:workflow.config.write')->name('store');
                Route::get('/{department}', [DepartmentController::class, 'show'])->middleware('permission:workflow.config.read')->name('show');
                Route::get('/{department}/edit', [DepartmentController::class, 'edit'])->middleware('permission:workflow.config.write')->name('edit');
                Route::put('/{department}', [DepartmentController::class, 'write'])->middleware('permission:workflow.config.write')->name('update');
                Route::delete('/{department}', [DepartmentController::class, 'unlink'])->middleware('permission:workflow.config.unlink')->name('delete');
                Route::post('/{department}/comment', [DepartmentController::class, 'addComment'])->middleware('permission:workflow.config.write')->name('comment');
            });

            Route::prefix('managers')->name('managers.')->group(function () {
                Route::get('/', [ManagerController::class, 'read'])->middleware('permission:workflow.config.read')->name('index');
                Route::get('/{manager}', [ManagerController::class, 'show'])->middleware('permission:workflow.config.read')->name('show');
                Route::post('/{manager}/comment', [ManagerController::class, 'addComment'])->middleware('permission:workflow.config.write')->name('comment');
            });

            Route::prefix('users')->name('users.')->group(function () {
                Route::get('/', [WorkflowUserController::class, 'read'])->middleware('permission:workflow.config.read')->name('index');
                Route::get('/{user}', [WorkflowUserController::class, 'show'])->middleware('permission:workflow.config.read')->name('show');
                Route::get('/{user}/edit', [WorkflowUserController::class, 'edit'])->middleware('permission:workflow.config.write')->name('edit');
                Route::put('/{user}', [WorkflowUserController::class, 'write'])->middleware('permission:workflow.config.write')->name('update');
                Route::post('/{user}/comment', [WorkflowUserController::class, 'addComment'])->middleware('permission:workflow.config.write')->name('comment');
            });

            Route::prefix('ticket-templates')->name('ticket-templates.')->group(function () {
                Route::get('/', [TicketTemplateController::class, 'read'])->middleware('permission:workflow.config.read')->name('index');
                Route::get('/create', [TicketTemplateController::class, 'create'])->middleware('permission:workflow.config.write')->name('create');
                Route::post('/', [TicketTemplateController::class, 'store'])->middleware('permission:workflow.config.write')->name('store');
                Route::get('/{ticketTemplate}', [TicketTemplateController::class, 'show'])->middleware('permission:workflow.config.read')->name('show');
                Route::get('/{ticketTemplate}/edit', [TicketTemplateController::class, 'edit'])->middleware('permission:workflow.config.write')->name('edit');
                Route::put('/{ticketTemplate}', [TicketTemplateController::class, 'write'])->middleware('permission:workflow.config.write')->name('update');
                Route::delete('/{ticketTemplate}', [TicketTemplateController::class, 'unlink'])->middleware('permission:workflow.config.unlink')->name('delete');
                Route::post('/{ticketTemplate}/comment', [TicketTemplateController::class, 'addComment'])->middleware('permission:workflow.config.write')->name('comment');
            });

            Route::prefix('procedure-templates')->name('procedure-templates.')->group(function () {
                Route::get('/', [ProcedureTemplateController::class, 'read'])->middleware('permission:workflow.config.read')->name('index');
                Route::get('/create', [ProcedureTemplateController::class, 'create'])->middleware('permission:workflow.config.write')->name('create');
                Route::post('/', [ProcedureTemplateController::class, 'store'])->middleware('permission:workflow.config.write')->name('store');
                Route::get('/{procedureTemplate}', [ProcedureTemplateController::class, 'show'])->middleware('permission:workflow.config.read')->name('show');
                Route::get('/{procedureTemplate}/edit', [ProcedureTemplateController::class, 'edit'])->middleware('permission:workflow.config.write')->name('edit');
                Route::put('/{procedureTemplate}', [ProcedureTemplateController::class, 'write'])->middleware('permission:workflow.config.write')->name('update');
                Route::delete('/{procedureTemplate}', [ProcedureTemplateController::class, 'unlink'])->middleware('permission:workflow.config.unlink')->name('delete');
                Route::post('/{procedureTemplate}/comment', [ProcedureTemplateController::class, 'addComment'])->middleware('permission:workflow.config.write')->name('comment');
                Route::post('/{procedureTemplate}/steps', [ProcedureTemplateController::class, 'storeStep'])->middleware('permission:workflow.config.write')->name('steps.store');
                Route::get('/{procedureTemplate}/steps/{step}/edit', [ProcedureTemplateController::class, 'editStep'])->middleware('permission:workflow.config.write')->name('steps.edit');
                Route::put('/{procedureTemplate}/steps/{step}', [ProcedureTemplateController::class, 'updateStep'])->middleware('permission:workflow.config.write')->name('steps.update');
                Route::delete('/{procedureTemplate}/steps/{step}', [ProcedureTemplateController::class, 'destroyStep'])->middleware('permission:workflow.config.unlink')->name('steps.destroy');
            });

        });

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
            Route::get('/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.write')->name('edit');
            Route::put('/{role}', [RoleController::class, 'write'])->middleware('permission:roles.write')->name('update');
            Route::delete('/{role}', [RoleController::class, 'unlink'])->middleware('permission:roles.unlink')->name('delete');
        });

        // Permissions
        Route::get('/permissions', [PermissionController::class, 'read'])->middleware('permission:roles.read')->name('permissions.index');
    });
});
