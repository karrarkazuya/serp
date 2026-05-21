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
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\Employees\DepartmentController as EmployeeDepartmentController;
use App\Http\Controllers\Employees\JobController;
use App\Http\Controllers\Employees\WorkLocationController;
use App\Http\Controllers\Employees\ResourceCalendarController;
use App\Http\Controllers\Employees\EmployeeCategoryController;
use App\Http\Controllers\Employees\ContractController;
use App\Http\Controllers\Employees\DepartureReasonController;
use App\Http\Controllers\Employees\SkillTypeController;
use App\Http\Controllers\Employees\ResumeLineTypeController;
use App\Http\Controllers\Employees\EmploymentTypeController;
use App\Http\Controllers\Employees\BadgeController;
use App\Http\Controllers\Employees\ChallengeController;
use App\Http\Controllers\Employees\GoalController;
use App\Http\Controllers\Employees\EmployeeDocumentController;
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
use App\Http\Controllers\Api\Chatter\ChatterController;
use App\Http\Controllers\Chatter\ChatterFileController;
use App\Http\Controllers\Chat\ChatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
// Public shared-link route — no authentication required
Route::get('/share/{token}', [SharedLinkController::class, 'show'])->name('share.show');

// Contact avatar — no auth middleware; controller returns default image if unauthorized
Route::get('/contacts/avatar/{uuid}', [ContactController::class, 'avatar'])->name('contacts.avatar');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post')->middleware('throttle:10,1');
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
        Route::post('/direct/{user}', [ChatController::class, 'openDirect'])->name('direct');
        Route::get('/{room}', [ChatController::class, 'show'])->name('show');
        Route::post('/{room}/messages', [ChatController::class, 'store'])->name('store');
        Route::get('/{room}/files/{file}', [ChatController::class, 'file'])->name('file');
        Route::post('/{room}/members', [ChatController::class, 'addMember'])->name('members.add');
        Route::delete('/{room}/members/{user}', [ChatController::class, 'removeMember'])->name('members.remove');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/language', [ProfileController::class, 'updateLanguage'])->name('profile.language');
    Route::put('/profile/password', [ProfileController::class, 'changePassword'])->name('profile.password');

    // Company context switcher (navbar POST)
    Route::post('/company/switch', [CompanySwitchController::class, 'switch'])->name('company.switch');

    Route::get('/relation-dropdown/{table}', RelationLookupController::class)->name('relation-dropdown.lookup');

    // Chatter API (used by <x-chatter> component via fetch — must be web/session auth)
    Route::prefix('chatter')->name('api.chatter.')->group(function () {
        Route::get('/', [ChatterController::class, 'index'])->name('index');
        Route::post('/', [ChatterController::class, 'store'])->name('store');
        Route::get('/{chatterMessage}/file/{index}/{side}', [ChatterFileController::class, 'serve'])->name('file');
    });

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
    | Employees module
    |--------------------------------------------------------------------------
    */
    Route::prefix('employees')->name('employees.')->group(function () {
        // Main employees CRUD
        Route::get('/', [EmployeeController::class, 'read'])->middleware('permission:employees.read')->name('index');
        Route::get('/create', [EmployeeController::class, 'create'])->middleware('permission:employees.create')->name('create');
        Route::post('/', [EmployeeController::class, 'store'])->middleware('permission:employees.create')->name('store');
        Route::get('/check-link', [EmployeeController::class, 'checkLinkConflict'])->middleware('permission:employees.read')->name('check-link');

        // Departments (before /{employee} to avoid binding conflict)
        Route::prefix('departments')->name('departments.')->group(function () {
            Route::get('/', [EmployeeDepartmentController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create', [EmployeeDepartmentController::class, 'create'])->middleware('permission:employees.create')->name('create');
            Route::post('/', [EmployeeDepartmentController::class, 'store'])->middleware('permission:employees.create')->name('store');
            Route::get('/{department}', [EmployeeDepartmentController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{department}/edit', [EmployeeDepartmentController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{department}', [EmployeeDepartmentController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{department}/archive', [EmployeeDepartmentController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{department}/unarchive', [EmployeeDepartmentController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{department}', [EmployeeDepartmentController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
            Route::post('/{department}/comment', [EmployeeDepartmentController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');
        });

        // Job Positions
        Route::prefix('jobs')->name('jobs.')->group(function () {
            Route::get('/', [JobController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create', [JobController::class, 'create'])->middleware('permission:employees.create')->name('create');
            Route::post('/', [JobController::class, 'store'])->middleware('permission:employees.create')->name('store');
            Route::get('/{job}', [JobController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{job}/edit', [JobController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{job}', [JobController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{job}/archive', [JobController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{job}/unarchive', [JobController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{job}', [JobController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
            Route::post('/{job}/comment', [JobController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');
        });

        // Work Locations
        Route::prefix('work-locations')->name('work-locations.')->group(function () {
            Route::get('/', [WorkLocationController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create', [WorkLocationController::class, 'create'])->middleware('permission:employees.create')->name('create');
            Route::post('/', [WorkLocationController::class, 'store'])->middleware('permission:employees.create')->name('store');
            Route::get('/{location}', [WorkLocationController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{location}/edit', [WorkLocationController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{location}', [WorkLocationController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{location}/archive', [WorkLocationController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{location}/unarchive', [WorkLocationController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{location}', [WorkLocationController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
            Route::post('/{location}/comment', [WorkLocationController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');
        });

        // Working Schedules
        Route::prefix('schedules')->name('schedules.')->group(function () {
            Route::get('/', [ResourceCalendarController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create', [ResourceCalendarController::class, 'create'])->middleware('permission:employees.create')->name('create');
            Route::post('/', [ResourceCalendarController::class, 'store'])->middleware('permission:employees.create')->name('store');
            Route::get('/{schedule}', [ResourceCalendarController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{schedule}/edit', [ResourceCalendarController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{schedule}', [ResourceCalendarController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{schedule}/archive', [ResourceCalendarController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{schedule}/unarchive', [ResourceCalendarController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{schedule}', [ResourceCalendarController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
            Route::post('/{schedule}/comment', [ResourceCalendarController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');
        });

        // Categories / Tags
        Route::prefix('categories')->name('categories.')->group(function () {
            Route::get('/', [EmployeeCategoryController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create', [EmployeeCategoryController::class, 'create'])->middleware('permission:employees.create')->name('create');
            Route::post('/', [EmployeeCategoryController::class, 'store'])->middleware('permission:employees.create')->name('store');
            Route::get('/{employeeCategory}', [EmployeeCategoryController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{employeeCategory}/edit', [EmployeeCategoryController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{employeeCategory}', [EmployeeCategoryController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{employeeCategory}/archive', [EmployeeCategoryController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{employeeCategory}/unarchive', [EmployeeCategoryController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{employeeCategory}', [EmployeeCategoryController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
            Route::post('/{employeeCategory}/comment', [EmployeeCategoryController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');
        });

        // Departure Reasons
        Route::prefix('departure-reasons')->name('departure-reasons.')->group(function () {
            Route::get('/', [DepartureReasonController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create', [DepartureReasonController::class, 'create'])->middleware('permission:employees.write')->name('create');
            Route::post('/', [DepartureReasonController::class, 'store'])->middleware('permission:employees.write')->name('store');
            Route::get('/{departureReason}', [DepartureReasonController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{departureReason}/edit', [DepartureReasonController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{departureReason}', [DepartureReasonController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{departureReason}/archive', [DepartureReasonController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{departureReason}/unarchive', [DepartureReasonController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{departureReason}', [DepartureReasonController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
            Route::post('/{departureReason}/comment', [DepartureReasonController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');
        });

        // Skill Types
        Route::prefix('skill-types')->name('skill-types.')->group(function () {
            Route::get('/', [SkillTypeController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create', [SkillTypeController::class, 'create'])->middleware('permission:employees.write')->name('create');
            Route::post('/', [SkillTypeController::class, 'store'])->middleware('permission:employees.write')->name('store');
            Route::get('/{skillType}', [SkillTypeController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{skillType}/edit', [SkillTypeController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{skillType}', [SkillTypeController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{skillType}/archive', [SkillTypeController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{skillType}/unarchive', [SkillTypeController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{skillType}', [SkillTypeController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
            Route::post('/{skillType}/comment', [SkillTypeController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');
        });

        // Resume Line Types
        Route::prefix('resume-line-types')->name('resume-line-types.')->group(function () {
            Route::get('/', [ResumeLineTypeController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create', [ResumeLineTypeController::class, 'create'])->middleware('permission:employees.write')->name('create');
            Route::post('/', [ResumeLineTypeController::class, 'store'])->middleware('permission:employees.write')->name('store');
            Route::get('/{resumeLineType}', [ResumeLineTypeController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{resumeLineType}/edit', [ResumeLineTypeController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{resumeLineType}', [ResumeLineTypeController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{resumeLineType}/archive', [ResumeLineTypeController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{resumeLineType}/unarchive', [ResumeLineTypeController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{resumeLineType}', [ResumeLineTypeController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
            Route::post('/{resumeLineType}/comment', [ResumeLineTypeController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');
        });

        // Employment Types
        Route::prefix('employment-types')->name('employment-types.')->group(function () {
            Route::get('/', [EmploymentTypeController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create', [EmploymentTypeController::class, 'create'])->middleware('permission:employees.write')->name('create');
            Route::post('/', [EmploymentTypeController::class, 'store'])->middleware('permission:employees.write')->name('store');
            Route::get('/{employmentType}', [EmploymentTypeController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{employmentType}/edit', [EmploymentTypeController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{employmentType}', [EmploymentTypeController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{employmentType}/archive', [EmploymentTypeController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{employmentType}/unarchive', [EmploymentTypeController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{employmentType}', [EmploymentTypeController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
            Route::post('/{employmentType}/comment', [EmploymentTypeController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');
        });

        // Badges
        Route::prefix('badges')->name('badges.')->group(function () {
            Route::get('/', [BadgeController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create', [BadgeController::class, 'create'])->middleware('permission:employees.write')->name('create');
            Route::post('/', [BadgeController::class, 'store'])->middleware('permission:employees.write')->name('store');
            Route::get('/{badge}', [BadgeController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{badge}/edit', [BadgeController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{badge}', [BadgeController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{badge}/archive', [BadgeController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{badge}/unarchive', [BadgeController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{badge}', [BadgeController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
            Route::post('/{badge}/comment', [BadgeController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');
        });

        // Challenges
        Route::prefix('challenges')->name('challenges.')->group(function () {
            Route::get('/', [ChallengeController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create', [ChallengeController::class, 'create'])->middleware('permission:employees.write')->name('create');
            Route::post('/', [ChallengeController::class, 'store'])->middleware('permission:employees.write')->name('store');
            Route::get('/{challenge}', [ChallengeController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{challenge}/edit', [ChallengeController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{challenge}', [ChallengeController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{challenge}/archive', [ChallengeController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{challenge}/unarchive', [ChallengeController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{challenge}', [ChallengeController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
            Route::post('/{challenge}/comment', [ChallengeController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');
        });

        // Goals
        Route::prefix('goals')->name('goals.')->group(function () {
            Route::get('/', [GoalController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create', [GoalController::class, 'create'])->middleware('permission:employees.write')->name('create');
            Route::post('/', [GoalController::class, 'store'])->middleware('permission:employees.write')->name('store');
            Route::get('/{goal}', [GoalController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{goal}/edit', [GoalController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{goal}', [GoalController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{goal}/archive', [GoalController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{goal}/unarchive', [GoalController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{goal}', [GoalController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
            Route::post('/{goal}/comment', [GoalController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');
        });

        // Employee CRUD (after fixed sub-routes)
        Route::get('/avatar/{uuid}', [EmployeeController::class, 'serveAvatar'])->middleware('permission:employees.read')->name('avatar');
        Route::get('/{employee}', [EmployeeController::class, 'show'])->middleware('permission:employees.read')->name('show');
        Route::get('/{employee}/edit', [EmployeeController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
        Route::put('/{employee}', [EmployeeController::class, 'write'])->middleware('permission:employees.write')->name('update');
        Route::patch('/{employee}/archive', [EmployeeController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
        Route::patch('/{employee}/unarchive', [EmployeeController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
        Route::delete('/{employee}', [EmployeeController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
        Route::post('/{employee}/comment', [EmployeeController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');

        // Documents (nested under employee)
        Route::post('/{employee}/documents', [EmployeeDocumentController::class, 'store'])->middleware('permission:employees.write')->name('documents.store');
        Route::delete('/{employee}/documents/{document}', [EmployeeDocumentController::class, 'unlink'])->middleware('permission:employees.write')->name('documents.delete');
        Route::get('/{employee}/documents/{document}/download', [EmployeeDocumentController::class, 'download'])->middleware('permission:employees.read')->name('documents.download');
        Route::get('/{employee}/documents/{document}/preview', [EmployeeDocumentController::class, 'preview'])->middleware('permission:employees.read')->name('documents.preview');

        // Contracts (nested under employee)
        Route::post('/{employee}/contracts', [ContractController::class, 'store'])->middleware('permission:employees.write')->name('contracts.store');
        Route::get('/{employee}/contracts/{contract}/image', [ContractController::class, 'serveImage'])->middleware('permission:employees.read')->name('contracts.image');
        Route::put('/{employee}/contracts/{contract}', [ContractController::class, 'write'])->middleware('permission:employees.write')->name('contracts.update');
        Route::patch('/{employee}/contracts/{contract}/set-active', [ContractController::class, 'setActive'])->middleware('permission:employees.write')->name('contracts.set-active');
        Route::delete('/{employee}/contracts/{contract}', [ContractController::class, 'unlink'])->middleware('permission:employees.unlink')->name('contracts.delete');
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
            Route::get('/{ticket}/inputs/{recordInput}/file', [TicketController::class, 'downloadInputFile'])->middleware('permission:workflow.tickets.read')->name('input-file');
            Route::delete('/{ticket}/inputs/{recordInput}/file', [TicketController::class, 'deleteInputFile'])->middleware('permission:workflow.tickets.write')->name('input-file.delete');
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
            Route::post('/{ticket}/sub-procedures/{line}/start', [TicketController::class, 'startSubProcedure'])->middleware('permission:workflow.tickets.write')->name('sub-procedures.start');
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
                Route::get('/{procedureTemplate}/steps/lookup', [ProcedureTemplateController::class, 'stepsLookup'])->middleware('permission:workflow.config.read')->name('steps.lookup');
                Route::get('/{procedureTemplate}/flowchart', [ProcedureTemplateController::class, 'flowchart'])->middleware('permission:workflow.config.read')->name('flowchart');
                Route::post('/{procedureTemplate}/flowchart/layout', [ProcedureTemplateController::class, 'saveFlowchartLayout'])->middleware('permission:workflow.config.write')->name('flowchart.layout.save');
                Route::post('/{procedureTemplate}/flowchart/layout/reset', [ProcedureTemplateController::class, 'resetFlowchartLayout'])->middleware('permission:workflow.config.write')->name('flowchart.layout.reset');
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
            Route::get('/{role}', [RoleController::class, 'show'])->middleware('permission:roles.read')->name('show');
            Route::get('/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.write')->name('edit');
            Route::put('/{role}', [RoleController::class, 'write'])->middleware('permission:roles.write')->name('update');
            Route::delete('/{role}', [RoleController::class, 'unlink'])->middleware('permission:roles.unlink')->name('delete');
        });

        // Permissions
        Route::get('/permissions', [PermissionController::class, 'read'])->middleware('permission:roles.read')->name('permissions.index');
    });
});
