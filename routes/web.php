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
use App\Http\Controllers\Employees\AttendanceController;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\Employees\EmployeeRequestController;
use App\Http\Controllers\Employees\PlannedScheduleController;
use App\Http\Controllers\Employees\RequestBalanceConfigController;
use App\Http\Controllers\Employees\RequestSubtypeController;
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
use App\Http\Controllers\Employees\EmployeeCertificateController;
use App\Http\Controllers\Employees\EmployeePositionController;
use App\Http\Controllers\Employees\EmployeeBonusController;
use App\Http\Controllers\Employees\EmployeeAppreciationController;
use App\Http\Controllers\Employees\EmployeeSanctionController;
use App\Http\Controllers\Employees\EmployeeRewardController;
use App\Http\Controllers\Employees\EmployeeJobGradeController;
use App\Http\Controllers\Components\RelationLookupController;
use App\Http\Controllers\Settings\CompanyController;
use App\Http\Controllers\Settings\PermissionController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\UserController;
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
use App\Http\Controllers\FileController;
use App\Http\Controllers\ExportController;
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
*/
Route::middleware('auth')->group(function () {

    // Unified file serving — UUID-based, permission-aware
    Route::get('/files/{uuid}', [FileController::class, 'serve'])->name('files.serve');
    Route::get('/files/{uuid}/thumbnail', [FileController::class, 'thumbnail'])->name('files.thumbnail');

    // Generic export — permission checked dynamically inside the controller.
    // Throttled because exports run heavy queries + build files; cap per-user
    // to 20/min so one user can't saturate workers.
    Route::post('/export', [ExportController::class, 'export'])
        ->middleware('throttle:20,1')
        ->name('export');

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

        // ─ Leave / Time-off / Overtime requests ────────────────────────────
        // Self-service personal queue (employee + their pending-approval list).
        Route::get('/my-requests', [EmployeeRequestController::class, 'myIndex'])
            ->middleware('permission:attendance.self.request')->name('my-requests');

        // HR + self-service list/create. The controller distinguishes by
        // whichever permission the caller holds.
        Route::prefix('requests')->name('requests.')->group(function () {
            Route::get('/',                 [EmployeeRequestController::class, 'read'])  ->middleware('permission:attendance.requests.read')                   ->name('index');
            Route::get('/create',           [EmployeeRequestController::class, 'create'])->middleware('permission_any:attendance.requests.write,attendance.self.request')->name('create');
            Route::post('/',                [EmployeeRequestController::class, 'store']) ->middleware('permission_any:attendance.requests.write,attendance.self.request')->name('store');
            Route::get('/{employeeRequest}',[EmployeeRequestController::class, 'show'])  ->middleware('permission_any:attendance.requests.read,attendance.self.request') ->name('show');
            Route::post('/{employeeRequest}/decide',  [EmployeeRequestController::class, 'decide'])    ->middleware('permission_any:attendance.requests.write,attendance.hr_approve,attendance.self.request')->name('decide');
            Route::post('/{employeeRequest}/comment', [EmployeeRequestController::class, 'addComment'])->middleware('permission_any:attendance.requests.read,attendance.self.request')                                ->name('comment');
        });

        // Request subtypes config
        Route::prefix('request-subtypes')->name('request-subtypes.')->group(function () {
            Route::get('/',                       [RequestSubtypeController::class, 'read'])     ->middleware('permission:attendance.requests.config')->name('index');
            Route::get('/create',                 [RequestSubtypeController::class, 'create'])   ->middleware('permission:attendance.requests.config')->name('create');
            Route::post('/',                      [RequestSubtypeController::class, 'store'])    ->middleware('permission:attendance.requests.config')->name('store');
            Route::get('/{subtype}',              [RequestSubtypeController::class, 'show'])     ->middleware('permission:attendance.requests.config')->name('show');
            Route::get('/{subtype}/edit',         [RequestSubtypeController::class, 'edit'])     ->middleware('permission:attendance.requests.config')->name('edit');
            Route::put('/{subtype}',              [RequestSubtypeController::class, 'write'])    ->middleware('permission:attendance.requests.config')->name('update');
            Route::patch('/{subtype}/archive',    [RequestSubtypeController::class, 'archive'])  ->middleware('permission:attendance.requests.config')->name('archive');
            Route::patch('/{subtype}/unarchive',  [RequestSubtypeController::class, 'unarchive'])->middleware('permission:attendance.requests.config')->name('unarchive');
            Route::delete('/{subtype}',           [RequestSubtypeController::class, 'unlink'])   ->middleware('permission:attendance.requests.config')->name('delete');
            Route::post('/{subtype}/comment',     [RequestSubtypeController::class, 'addComment'])->middleware('permission:attendance.requests.config')->name('comment');
        });

        // Balance config (single page per company)
        Route::prefix('request-balance-config')->name('request-balance-config.')->group(function () {
            Route::get('/',  [RequestBalanceConfigController::class, 'show'])->middleware('permission:attendance.requests.config')->name('show');
            Route::post('/', [RequestBalanceConfigController::class, 'save'])->middleware('permission:attendance.requests.config')->name('save');
        });

        // Planned Schedule (per-employee — nested under /{employee})
        // Note: no delete route on purpose — only the midnight cron removes
        // planned days (after they pass and are recorded in attendance).
        Route::prefix('{employee}/planned-schedule')->name('planned-schedule.')->group(function () {
            Route::post('/day',     [PlannedScheduleController::class, 'setDay'])      ->middleware('permission:planned_schedules.write')->name('set-day');
            Route::post('/pattern', [PlannedScheduleController::class, 'applyPattern'])->middleware('permission:planned_schedules.write')->name('pattern');
        });

        // Attendance (before /{employee} to avoid binding conflict)
        Route::prefix('attendances')->name('attendances.')->group(function () {
            Route::get('/', [AttendanceController::class, 'read'])->middleware('permission:attendance.read')->name('index');
            Route::get('/create', [AttendanceController::class, 'create'])->middleware('permission:attendance.create')->name('create');
            Route::post('/', [AttendanceController::class, 'store'])->middleware('permission:attendance.create')->name('store');
            Route::get('/{attendance}', [AttendanceController::class, 'show'])->middleware('permission:attendance.read')->name('show');
            Route::get('/{attendance}/edit', [AttendanceController::class, 'edit'])->middleware('permission:attendance.write')->name('edit');
            Route::put('/{attendance}', [AttendanceController::class, 'write'])->middleware('permission:attendance.write')->name('update');
            // No delete — attendance records are immutable history.
            Route::post('/{attendance}/comment', [AttendanceController::class, 'addComment'])->middleware('permission:attendance.write')->name('comment');
        });

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

        // Positions — standalone sub-module (before /{employee} to avoid binding conflict)
        Route::prefix('positions')->name('positions.')->group(function () {
            Route::get('/',                       [EmployeePositionController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create',                 [EmployeePositionController::class, 'create'])->middleware('permission:employees.write')->name('create');
            Route::post('/',                      [EmployeePositionController::class, 'store'])->middleware('permission:employees.write')->name('store');
            Route::get('/{position}',                                   [EmployeePositionController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{position}/edit',                              [EmployeePositionController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{position}',                                   [EmployeePositionController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{position}/archive',                         [EmployeePositionController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{position}/unarchive',                       [EmployeePositionController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{position}',                                [EmployeePositionController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
            Route::put('/{position}/employees',                          [EmployeePositionController::class, 'syncEmployees'])->middleware('permission:employees.write')->name('employees.sync');
            Route::post('/{position}/comment',                          [EmployeePositionController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');
        });

        // Documents — standalone sub-module (before /{employee} to avoid binding conflict)
        Route::prefix('documents')->name('documents.')->group(function () {
            Route::get('/',                     [EmployeeDocumentController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create',               [EmployeeDocumentController::class, 'create'])->middleware('permission:employees.write')->name('create');
            Route::post('/',                    [EmployeeDocumentController::class, 'store'])->middleware('permission:employees.write')->name('store');
            Route::get('/{document}',           [EmployeeDocumentController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{document}/edit',      [EmployeeDocumentController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{document}',           [EmployeeDocumentController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{document}/archive', [EmployeeDocumentController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{document}/unarchive',[EmployeeDocumentController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{document}',        [EmployeeDocumentController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
        });

        // Certificates — standalone sub-module (before /{employee} to avoid binding conflict)
        Route::prefix('certificates')->name('certificates.')->group(function () {
            Route::get('/',                        [EmployeeCertificateController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create',                  [EmployeeCertificateController::class, 'create'])->middleware('permission:employees.write')->name('create');
            Route::post('/',                       [EmployeeCertificateController::class, 'store'])->middleware('permission:employees.write')->name('store');
            Route::get('/{certificate}',           [EmployeeCertificateController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{certificate}/edit',      [EmployeeCertificateController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{certificate}',           [EmployeeCertificateController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{certificate}/archive', [EmployeeCertificateController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{certificate}/unarchive',[EmployeeCertificateController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{certificate}',        [EmployeeCertificateController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
            Route::post('/{certificate}/comment',  [EmployeeCertificateController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');
        });

        // Bonuses
        Route::prefix('bonuses')->name('bonuses.')->group(function () {
            Route::get('/',                    [EmployeeBonusController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create',              [EmployeeBonusController::class, 'create'])->middleware('permission:employees.write')->name('create');
            Route::post('/',                   [EmployeeBonusController::class, 'store'])->middleware('permission:employees.write')->name('store');
            Route::get('/{bonus}',             [EmployeeBonusController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{bonus}/edit',        [EmployeeBonusController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{bonus}/employees',    [EmployeeBonusController::class, 'syncEmployees'])->middleware('permission:employees.write')->name('employees.sync');
            Route::put('/{bonus}',             [EmployeeBonusController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{bonus}/archive',   [EmployeeBonusController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{bonus}/unarchive', [EmployeeBonusController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{bonus}',          [EmployeeBonusController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
            Route::post('/{bonus}/comment',    [EmployeeBonusController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');
            Route::post('/{bonus}/document',   [EmployeeBonusController::class, 'replaceDocument'])->middleware('permission:employees.write')->name('document.replace');
            Route::delete('/{bonus}/document', [EmployeeBonusController::class, 'deleteDocument'])->middleware('permission:employees.write')->name('document.delete');
        });

        // Appreciations
        Route::prefix('appreciations')->name('appreciations.')->group(function () {
            Route::get('/',                           [EmployeeAppreciationController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create',                     [EmployeeAppreciationController::class, 'create'])->middleware('permission:employees.write')->name('create');
            Route::post('/',                          [EmployeeAppreciationController::class, 'store'])->middleware('permission:employees.write')->name('store');
            Route::get('/{appreciation}',             [EmployeeAppreciationController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{appreciation}/edit',        [EmployeeAppreciationController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{appreciation}/employees',   [EmployeeAppreciationController::class, 'syncEmployees'])->middleware('permission:employees.write')->name('employees.sync');
            Route::put('/{appreciation}',             [EmployeeAppreciationController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{appreciation}/archive',   [EmployeeAppreciationController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{appreciation}/unarchive', [EmployeeAppreciationController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{appreciation}',          [EmployeeAppreciationController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
            Route::post('/{appreciation}/comment',    [EmployeeAppreciationController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');
            Route::post('/{appreciation}/document',   [EmployeeAppreciationController::class, 'replaceDocument'])->middleware('permission:employees.write')->name('document.replace');
            Route::delete('/{appreciation}/document', [EmployeeAppreciationController::class, 'deleteDocument'])->middleware('permission:employees.write')->name('document.delete');
        });

        // Sanctions
        Route::prefix('sanctions')->name('sanctions.')->group(function () {
            Route::get('/',                      [EmployeeSanctionController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create',                [EmployeeSanctionController::class, 'create'])->middleware('permission:employees.write')->name('create');
            Route::post('/',                     [EmployeeSanctionController::class, 'store'])->middleware('permission:employees.write')->name('store');
            Route::get('/{sanction}',            [EmployeeSanctionController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{sanction}/edit',       [EmployeeSanctionController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{sanction}/employees',  [EmployeeSanctionController::class, 'syncEmployees'])->middleware('permission:employees.write')->name('employees.sync');
            Route::put('/{sanction}',            [EmployeeSanctionController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{sanction}/archive',  [EmployeeSanctionController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{sanction}/unarchive',[EmployeeSanctionController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{sanction}',         [EmployeeSanctionController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
            Route::post('/{sanction}/comment',   [EmployeeSanctionController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');
            Route::post('/{sanction}/document',  [EmployeeSanctionController::class, 'replaceDocument'])->middleware('permission:employees.write')->name('document.replace');
            Route::delete('/{sanction}/document',[EmployeeSanctionController::class, 'deleteDocument'])->middleware('permission:employees.write')->name('document.delete');
        });

        // Rewards
        Route::prefix('rewards')->name('rewards.')->group(function () {
            Route::get('/',                     [EmployeeRewardController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create',               [EmployeeRewardController::class, 'create'])->middleware('permission:employees.write')->name('create');
            Route::post('/',                    [EmployeeRewardController::class, 'store'])->middleware('permission:employees.write')->name('store');
            Route::get('/{reward}',             [EmployeeRewardController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{reward}/edit',        [EmployeeRewardController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{reward}/employees',   [EmployeeRewardController::class, 'syncEmployees'])->middleware('permission:employees.write')->name('employees.sync');
            Route::put('/{reward}',             [EmployeeRewardController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{reward}/archive',   [EmployeeRewardController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{reward}/unarchive', [EmployeeRewardController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{reward}',          [EmployeeRewardController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
            Route::post('/{reward}/comment',    [EmployeeRewardController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');
            Route::post('/{reward}/document',   [EmployeeRewardController::class, 'replaceDocument'])->middleware('permission:employees.write')->name('document.replace');
            Route::delete('/{reward}/document', [EmployeeRewardController::class, 'deleteDocument'])->middleware('permission:employees.write')->name('document.delete');
        });

        // Job Grades
        Route::prefix('job-grades')->name('job-grades.')->group(function () {
            Route::get('/',                       [EmployeeJobGradeController::class, 'read'])->middleware('permission:employees.read')->name('index');
            Route::get('/create',                 [EmployeeJobGradeController::class, 'create'])->middleware('permission:employees.write')->name('create');
            Route::post('/',                      [EmployeeJobGradeController::class, 'store'])->middleware('permission:employees.write')->name('store');
            Route::get('/{jobGrade}',             [EmployeeJobGradeController::class, 'show'])->middleware('permission:employees.read')->name('show');
            Route::get('/{jobGrade}/edit',        [EmployeeJobGradeController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
            Route::put('/{jobGrade}/employees',   [EmployeeJobGradeController::class, 'syncEmployees'])->middleware('permission:employees.write')->name('employees.sync');
            Route::put('/{jobGrade}',             [EmployeeJobGradeController::class, 'write'])->middleware('permission:employees.write')->name('update');
            Route::patch('/{jobGrade}/archive',   [EmployeeJobGradeController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
            Route::patch('/{jobGrade}/unarchive', [EmployeeJobGradeController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
            Route::delete('/{jobGrade}',          [EmployeeJobGradeController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
            Route::post('/{jobGrade}/comment',    [EmployeeJobGradeController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');
        });

        // Employee CRUD (after all fixed sub-routes)
        Route::get('/avatar/{uuid}', [EmployeeController::class, 'serveAvatar'])->middleware('permission:employees.read')->name('avatar');
        Route::get('/{employee}', [EmployeeController::class, 'show'])->middleware('permission:employees.read')->name('show');
        Route::get('/{employee}/edit', [EmployeeController::class, 'edit'])->middleware('permission:employees.write')->name('edit');
        Route::put('/{employee}', [EmployeeController::class, 'write'])->middleware('permission:employees.write')->name('update');
        Route::patch('/{employee}/archive', [EmployeeController::class, 'archive'])->middleware('permission:employees.write')->name('archive');
        Route::patch('/{employee}/unarchive', [EmployeeController::class, 'unarchive'])->middleware('permission:employees.write')->name('unarchive');
        Route::delete('/{employee}', [EmployeeController::class, 'unlink'])->middleware('permission:employees.unlink')->name('delete');
        Route::post('/{employee}/comment', [EmployeeController::class, 'addComment'])->middleware('permission:employees.write')->name('comment');

        // Documents — inline helpers (from employee edit page)
        Route::post('/{employee}/documents', [EmployeeDocumentController::class, 'employeeStore'])->middleware('permission:employees.write')->name('employee-docs.store');
        Route::delete('/{employee}/documents/{document}', [EmployeeDocumentController::class, 'employeeUnlink'])->middleware('permission:employees.write')->name('employee-docs.delete');
        Route::get('/{employee}/documents/{document}/download', [EmployeeDocumentController::class, 'download'])->middleware('permission:employees.read')->name('employee-docs.download');
        Route::get('/{employee}/documents/{document}/preview', [EmployeeDocumentController::class, 'preview'])->middleware('permission:employees.read')->name('employee-docs.preview');

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
    | Accounting module (Unified Accounting System)
    |--------------------------------------------------------------------------
    */
    Route::prefix('accounting')->name('accounting.')->group(function () {

        // Dashboard / overview
        Route::get('/', [\App\Http\Controllers\Accounting\AccountingDashboardController::class, 'index'])
            ->middleware('permission:accounting.read')->name('dashboard');

        // Chart of Accounts
        Route::prefix('accounts')->name('accounts.')->group(function () {
            Route::get('/',              [\App\Http\Controllers\Accounting\AccountController::class, 'read'])     ->middleware('permission:accounting.read')   ->name('index');
            Route::get('/create',        [\App\Http\Controllers\Accounting\AccountController::class, 'create'])   ->middleware('permission:accounting.create') ->name('create');
            Route::post('/',             [\App\Http\Controllers\Accounting\AccountController::class, 'store'])    ->middleware('permission:accounting.create') ->name('store');
            Route::get('/{account}',           [\App\Http\Controllers\Accounting\AccountController::class, 'show'])      ->middleware('permission:accounting.read')   ->name('show');
            Route::get('/{account}/edit',      [\App\Http\Controllers\Accounting\AccountController::class, 'edit'])      ->middleware('permission:accounting.write')  ->name('edit');
            Route::put('/{account}',           [\App\Http\Controllers\Accounting\AccountController::class, 'write'])     ->middleware('permission:accounting.write')  ->name('update');
            Route::patch('/{account}/archive', [\App\Http\Controllers\Accounting\AccountController::class, 'archive'])   ->middleware('permission:accounting.write')  ->name('archive');
            Route::patch('/{account}/unarchive', [\App\Http\Controllers\Accounting\AccountController::class, 'unarchive'])->middleware('permission:accounting.write') ->name('unarchive');
            Route::delete('/{account}',        [\App\Http\Controllers\Accounting\AccountController::class, 'unlink'])    ->middleware('permission:accounting.unlink') ->name('delete');
            Route::post('/{account}/comment',  [\App\Http\Controllers\Accounting\AccountController::class, 'addComment'])->middleware('permission:accounting.write')  ->name('comment');
        });

        // Journals
        Route::prefix('journals')->name('journals.')->group(function () {
            Route::get('/',              [\App\Http\Controllers\Accounting\AccountJournalController::class, 'read'])     ->middleware('permission:accounting.read')   ->name('index');
            Route::get('/create',        [\App\Http\Controllers\Accounting\AccountJournalController::class, 'create'])   ->middleware('permission:accounting.create') ->name('create');
            Route::post('/',             [\App\Http\Controllers\Accounting\AccountJournalController::class, 'store'])    ->middleware('permission:accounting.create') ->name('store');
            Route::get('/{journal}',           [\App\Http\Controllers\Accounting\AccountJournalController::class, 'show'])      ->middleware('permission:accounting.read')   ->name('show');
            Route::get('/{journal}/edit',      [\App\Http\Controllers\Accounting\AccountJournalController::class, 'edit'])      ->middleware('permission:accounting.write')  ->name('edit');
            Route::put('/{journal}',           [\App\Http\Controllers\Accounting\AccountJournalController::class, 'write'])     ->middleware('permission:accounting.write')  ->name('update');
            Route::patch('/{journal}/archive', [\App\Http\Controllers\Accounting\AccountJournalController::class, 'archive'])   ->middleware('permission:accounting.write')  ->name('archive');
            Route::patch('/{journal}/unarchive', [\App\Http\Controllers\Accounting\AccountJournalController::class, 'unarchive'])->middleware('permission:accounting.write') ->name('unarchive');
            Route::delete('/{journal}',        [\App\Http\Controllers\Accounting\AccountJournalController::class, 'unlink'])    ->middleware('permission:accounting.unlink') ->name('delete');
            Route::post('/{journal}/comment',  [\App\Http\Controllers\Accounting\AccountJournalController::class, 'addComment'])->middleware('permission:accounting.write')  ->name('comment');
        });

        // Customer Invoices
        Route::prefix('invoices')->name('invoices.')->group(function () {
            Route::get('/',                  [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'invoices'])      ->middleware('permission:accounting.read')   ->name('index');
            Route::get('/create',            [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'createInvoice']) ->middleware('permission:accounting.create') ->name('create');
            Route::post('/',                 [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'storeInvoice'])  ->middleware('permission:accounting.create') ->name('store');
            Route::get('/{invoice}',         [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'showInvoice'])   ->middleware('permission:accounting.read')   ->name('show');
            Route::get('/{invoice}/edit',    [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'editInvoice'])   ->middleware('permission:accounting.write')  ->name('edit');
            Route::put('/{invoice}',         [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'updateInvoice']) ->middleware('permission:accounting.write')  ->name('update');
            Route::patch('/{invoice}/post',  [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'postInvoice'])   ->middleware('permission:accounting.post')   ->name('post');
            Route::patch('/{invoice}/pay',   [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'payInvoice'])    ->middleware('permission:accounting.post')   ->name('pay');
            Route::post('/{invoice}/credit-note', [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'creditInvoice'])->middleware('permission:accounting.post')->name('credit-note');
            Route::get('/{invoice}/print',   [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'printInvoice'])  ->middleware('permission:accounting.read')   ->name('print');
            Route::patch('/{invoice}/draft', [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'resetInvoice'])  ->middleware('permission:accounting.post')   ->name('reset-draft');
            Route::patch('/{invoice}/cancel', [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'cancelInvoice'])->middleware('permission:accounting.post')   ->name('cancel');
            Route::delete('/{invoice}',      [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'deleteInvoice']) ->middleware('permission:accounting.unlink') ->name('delete');
            Route::post('/{invoice}/comment', [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'commentInvoice'])->middleware('permission:accounting.write')  ->name('comment');
        });

        // Customer Credit Notes
        Route::prefix('credit-notes')->name('credit-notes.')->group(function () {
            Route::get('/',               [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'creditNotes'])      ->middleware('permission:accounting.read')   ->name('index');
            Route::get('/create',         [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'createCreditNote']) ->middleware('permission:accounting.create') ->name('create');
            Route::post('/',              [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'storeCreditNote'])  ->middleware('permission:accounting.create') ->name('store');
            Route::get('/{creditNote}',           [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'showCreditNote'])   ->middleware('permission:accounting.read')   ->name('show');
            Route::get('/{creditNote}/edit',      [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'editCreditNote'])   ->middleware('permission:accounting.write')  ->name('edit');
            Route::put('/{creditNote}',           [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'updateCreditNote']) ->middleware('permission:accounting.write')  ->name('update');
            Route::patch('/{creditNote}/post',    [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'postCreditNote'])   ->middleware('permission:accounting.post')   ->name('post');
            Route::patch('/{creditNote}/pay',     [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'payCreditNote'])    ->middleware('permission:accounting.post')   ->name('pay');
            Route::get('/{creditNote}/print',     [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'printCreditNote'])  ->middleware('permission:accounting.read')   ->name('print');
            Route::patch('/{creditNote}/draft',   [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'resetCreditNote'])  ->middleware('permission:accounting.post')   ->name('reset-draft');
            Route::patch('/{creditNote}/cancel',  [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'cancelCreditNote']) ->middleware('permission:accounting.post')   ->name('cancel');
            Route::delete('/{creditNote}',        [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'deleteCreditNote']) ->middleware('permission:accounting.unlink') ->name('delete');
            Route::post('/{creditNote}/comment',  [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'commentCreditNote'])->middleware('permission:accounting.write')  ->name('comment');
        });

        // Vendor Bills
        Route::prefix('bills')->name('bills.')->group(function () {
            Route::get('/',               [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'bills'])      ->middleware('permission:accounting.read')   ->name('index');
            Route::get('/create',         [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'createBill']) ->middleware('permission:accounting.create') ->name('create');
            Route::post('/',              [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'storeBill'])  ->middleware('permission:accounting.create') ->name('store');
            Route::get('/{bill}',         [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'showBill'])   ->middleware('permission:accounting.read')   ->name('show');
            Route::get('/{bill}/edit',    [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'editBill'])   ->middleware('permission:accounting.write')  ->name('edit');
            Route::put('/{bill}',         [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'updateBill']) ->middleware('permission:accounting.write')  ->name('update');
            Route::patch('/{bill}/post',  [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'postBill'])   ->middleware('permission:accounting.post')   ->name('post');
            Route::patch('/{bill}/pay',   [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'payBill'])    ->middleware('permission:accounting.post')   ->name('pay');
            Route::post('/{bill}/credit-note', [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'creditBill'])->middleware('permission:accounting.post')->name('credit-note');
            Route::get('/{bill}/print',   [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'printBill'])  ->middleware('permission:accounting.read')   ->name('print');
            Route::patch('/{bill}/draft', [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'resetBill'])  ->middleware('permission:accounting.post')   ->name('reset-draft');
            Route::patch('/{bill}/cancel', [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'cancelBill'])->middleware('permission:accounting.post')   ->name('cancel');
            Route::delete('/{bill}',      [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'deleteBill']) ->middleware('permission:accounting.unlink') ->name('delete');
            Route::post('/{bill}/comment', [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'commentBill'])->middleware('permission:accounting.write')  ->name('comment');
        });

        // Vendor Refunds
        Route::prefix('refunds')->name('refunds.')->group(function () {
            Route::get('/',             [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'refunds'])       ->middleware('permission:accounting.read')   ->name('index');
            Route::get('/create',       [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'createRefund'])  ->middleware('permission:accounting.create') ->name('create');
            Route::post('/',            [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'storeRefund'])   ->middleware('permission:accounting.create') ->name('store');
            Route::get('/{refund}',          [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'showRefund'])   ->middleware('permission:accounting.read')   ->name('show');
            Route::get('/{refund}/edit',     [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'editRefund'])   ->middleware('permission:accounting.write')  ->name('edit');
            Route::put('/{refund}',          [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'updateRefund']) ->middleware('permission:accounting.write')  ->name('update');
            Route::patch('/{refund}/post',   [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'postRefund'])   ->middleware('permission:accounting.post')   ->name('post');
            Route::patch('/{refund}/pay',    [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'payRefund'])    ->middleware('permission:accounting.post')   ->name('pay');
            Route::get('/{refund}/print',    [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'printRefund'])  ->middleware('permission:accounting.read')   ->name('print');
            Route::patch('/{refund}/draft',  [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'resetRefund'])  ->middleware('permission:accounting.post')   ->name('reset-draft');
            Route::patch('/{refund}/cancel', [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'cancelRefund']) ->middleware('permission:accounting.post')   ->name('cancel');
            Route::delete('/{refund}',       [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'deleteRefund']) ->middleware('permission:accounting.unlink') ->name('delete');
            Route::post('/{refund}/comment', [\App\Http\Controllers\Accounting\AccountDocumentController::class, 'commentRefund'])->middleware('permission:accounting.write')  ->name('comment');
        });

        // Payments
        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/',        [\App\Http\Controllers\Accounting\AccountPaymentController::class, 'read'])       ->middleware('permission:accounting.read')   ->name('index');
            Route::get('/create',  [\App\Http\Controllers\Accounting\AccountPaymentController::class, 'create'])     ->middleware('permission:accounting.create') ->name('create');
            Route::post('/',       [\App\Http\Controllers\Accounting\AccountPaymentController::class, 'store'])      ->middleware('permission:accounting.create') ->name('store');
            Route::get('/{payment}',               [\App\Http\Controllers\Accounting\AccountPaymentController::class, 'show'])       ->middleware('permission:accounting.read')   ->name('show');
            // Posting-class actions: confirm posts the underlying account_move,
            // cancel/reset tear it down. All three require accounting.post — not
            // accounting.write — to keep parity with direct AccountMove posting
            // (otherwise accounting.write becomes "post journal entries via the
            // payment funnel", bypassing the accounting.post permission separation).
            Route::patch('/{payment}/confirm',     [\App\Http\Controllers\Accounting\AccountPaymentController::class, 'confirm'])    ->middleware('permission:accounting.post')   ->name('confirm');
            Route::patch('/{payment}/reset-draft', [\App\Http\Controllers\Accounting\AccountPaymentController::class, 'resetDraft']) ->middleware('permission:accounting.post')   ->name('reset-draft');
            Route::patch('/{payment}/cancel',      [\App\Http\Controllers\Accounting\AccountPaymentController::class, 'cancel'])     ->middleware('permission:accounting.post')   ->name('cancel');
            Route::delete('/{payment}',            [\App\Http\Controllers\Accounting\AccountPaymentController::class, 'unlink'])     ->middleware('permission:accounting.unlink') ->name('delete');
            Route::post('/{payment}/comment',      [\App\Http\Controllers\Accounting\AccountPaymentController::class, 'addComment']) ->middleware('permission:accounting.write')  ->name('comment');
        });

        // Journal Entries (manual moves in Phase 1)
        Route::prefix('moves')->name('moves.')->group(function () {
            Route::get('/',              [\App\Http\Controllers\Accounting\AccountMoveController::class, 'read'])     ->middleware('permission:accounting.read')   ->name('index');
            Route::get('/create',        [\App\Http\Controllers\Accounting\AccountMoveController::class, 'create'])   ->middleware('permission:accounting.create') ->name('create');
            Route::post('/',             [\App\Http\Controllers\Accounting\AccountMoveController::class, 'store'])    ->middleware('permission:accounting.create') ->name('store');
            Route::get('/{move}',              [\App\Http\Controllers\Accounting\AccountMoveController::class, 'show'])         ->middleware('permission:accounting.read')   ->name('show');
            Route::get('/{move}/edit',         [\App\Http\Controllers\Accounting\AccountMoveController::class, 'edit'])         ->middleware('permission:accounting.write')  ->name('edit');
            Route::put('/{move}',              [\App\Http\Controllers\Accounting\AccountMoveController::class, 'write'])        ->middleware('permission:accounting.write')  ->name('update');
            Route::patch('/{move}/post',       [\App\Http\Controllers\Accounting\AccountMoveController::class, 'post'])         ->middleware('permission:accounting.post')   ->name('post');
            Route::patch('/{move}/draft',      [\App\Http\Controllers\Accounting\AccountMoveController::class, 'resetToDraft']) ->middleware('permission:accounting.post')   ->name('reset-draft');
            Route::patch('/{move}/cancel',     [\App\Http\Controllers\Accounting\AccountMoveController::class, 'cancel'])       ->middleware('permission:accounting.post')   ->name('cancel');
            Route::post('/{move}/reverse',     [\App\Http\Controllers\Accounting\AccountMoveController::class, 'reverse'])      ->middleware('permission:accounting.post')   ->name('reverse');
            Route::delete('/{move}',           [\App\Http\Controllers\Accounting\AccountMoveController::class, 'unlink'])       ->middleware('permission:accounting.unlink') ->name('delete');
            Route::post('/{move}/comment',     [\App\Http\Controllers\Accounting\AccountMoveController::class, 'addComment'])   ->middleware('permission:accounting.write')  ->name('comment');
        });

        // Journal Items (move lines)
        Route::prefix('items')->name('items.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Accounting\AccountMoveLineController::class, 'read'])
                ->middleware('permission:accounting.read')->name('index');
        });

        // Taxes
        Route::prefix('taxes')->name('taxes.')->group(function () {
            Route::get('/',                [\App\Http\Controllers\Accounting\AccountTaxController::class, 'read'])      ->middleware('permission:accounting.read')   ->name('index');
            Route::get('/create',          [\App\Http\Controllers\Accounting\AccountTaxController::class, 'create'])    ->middleware('permission:accounting.create') ->name('create');
            Route::post('/',               [\App\Http\Controllers\Accounting\AccountTaxController::class, 'store'])     ->middleware('permission:accounting.create') ->name('store');
            Route::get('/{tax}',           [\App\Http\Controllers\Accounting\AccountTaxController::class, 'show'])      ->middleware('permission:accounting.read')   ->name('show');
            Route::get('/{tax}/edit',      [\App\Http\Controllers\Accounting\AccountTaxController::class, 'edit'])      ->middleware('permission:accounting.write')  ->name('edit');
            Route::put('/{tax}',           [\App\Http\Controllers\Accounting\AccountTaxController::class, 'write'])     ->middleware('permission:accounting.write')  ->name('update');
            Route::patch('/{tax}/archive', [\App\Http\Controllers\Accounting\AccountTaxController::class, 'archive'])   ->middleware('permission:accounting.write')  ->name('archive');
            Route::patch('/{tax}/unarchive',[\App\Http\Controllers\Accounting\AccountTaxController::class, 'unarchive'])->middleware('permission:accounting.write')  ->name('unarchive');
            Route::delete('/{tax}',        [\App\Http\Controllers\Accounting\AccountTaxController::class, 'unlink'])    ->middleware('permission:accounting.unlink') ->name('delete');
            Route::post('/{tax}/comment',  [\App\Http\Controllers\Accounting\AccountTaxController::class, 'addComment'])->middleware('permission:accounting.write')  ->name('comment');
        });

        // Exchange Rates (multi-currency)
        Route::prefix('currencies')->name('currencies.')->group(function () {
            Route::get('/',                        [\App\Http\Controllers\Accounting\CurrencyRateController::class, 'read'])   ->middleware('permission:accounting.read')  ->name('index');
            Route::get('/create',                  [\App\Http\Controllers\Accounting\CurrencyRateController::class, 'create']) ->middleware('permission:accounting.write') ->name('create');
            Route::post('/',                       [\App\Http\Controllers\Accounting\CurrencyRateController::class, 'store'])  ->middleware('permission:accounting.write') ->name('store');
            Route::get('/{currencyRate}',           [\App\Http\Controllers\Accounting\CurrencyRateController::class, 'show'])  ->middleware('permission:accounting.read')  ->name('show');
            Route::get('/{currencyRate}/edit',      [\App\Http\Controllers\Accounting\CurrencyRateController::class, 'edit'])  ->middleware('permission:accounting.write') ->name('edit');
            Route::put('/{currencyRate}',           [\App\Http\Controllers\Accounting\CurrencyRateController::class, 'write']) ->middleware('permission:accounting.write') ->name('update');
            Route::delete('/{currencyRate}',        [\App\Http\Controllers\Accounting\CurrencyRateController::class, 'unlink'])       ->middleware('permission:accounting.write') ->name('delete');
            Route::post('/{currencyRate}/comment',  [\App\Http\Controllers\Accounting\CurrencyRateController::class, 'addComment']) ->middleware('permission:accounting.write') ->name('comment');
        });

        // Payment Terms
        Route::prefix('payment-terms')->name('payment-terms.')->group(function () {
            Route::get('/',                       [\App\Http\Controllers\Accounting\AccountingPaymentTermController::class, 'read'])      ->middleware('permission:accounting.read')   ->name('index');
            Route::get('/create',                 [\App\Http\Controllers\Accounting\AccountingPaymentTermController::class, 'create'])    ->middleware('permission:accounting.create') ->name('create');
            Route::post('/',                      [\App\Http\Controllers\Accounting\AccountingPaymentTermController::class, 'store'])     ->middleware('permission:accounting.create') ->name('store');
            Route::get('/{paymentTerm}',          [\App\Http\Controllers\Accounting\AccountingPaymentTermController::class, 'show'])      ->middleware('permission:accounting.read')   ->name('show');
            Route::get('/{paymentTerm}/edit',     [\App\Http\Controllers\Accounting\AccountingPaymentTermController::class, 'edit'])      ->middleware('permission:accounting.write')  ->name('edit');
            Route::put('/{paymentTerm}',          [\App\Http\Controllers\Accounting\AccountingPaymentTermController::class, 'write'])     ->middleware('permission:accounting.write')  ->name('update');
            Route::patch('/{paymentTerm}/archive',   [\App\Http\Controllers\Accounting\AccountingPaymentTermController::class, 'archive'])   ->middleware('permission:accounting.write')  ->name('archive');
            Route::patch('/{paymentTerm}/unarchive', [\App\Http\Controllers\Accounting\AccountingPaymentTermController::class, 'unarchive']) ->middleware('permission:accounting.write')  ->name('unarchive');
            Route::delete('/{paymentTerm}',        [\App\Http\Controllers\Accounting\AccountingPaymentTermController::class, 'unlink'])    ->middleware('permission:accounting.unlink') ->name('delete');
            Route::post('/{paymentTerm}/comment',  [\App\Http\Controllers\Accounting\AccountingPaymentTermController::class, 'addComment'])->middleware('permission:accounting.write')  ->name('comment');
        });

        // Incoterms
        Route::prefix('incoterms')->name('incoterms.')->group(function () {
            Route::get('/',                  [\App\Http\Controllers\Accounting\AccountingIncotermController::class, 'read'])    ->middleware('permission:accounting.read')   ->name('index');
            Route::get('/create',            [\App\Http\Controllers\Accounting\AccountingIncotermController::class, 'create'])  ->middleware('permission:accounting.create') ->name('create');
            Route::post('/',                 [\App\Http\Controllers\Accounting\AccountingIncotermController::class, 'store'])   ->middleware('permission:accounting.create') ->name('store');
            Route::get('/{incoterm}',        [\App\Http\Controllers\Accounting\AccountingIncotermController::class, 'show'])    ->middleware('permission:accounting.read')   ->name('show');
            Route::get('/{incoterm}/edit',   [\App\Http\Controllers\Accounting\AccountingIncotermController::class, 'edit'])    ->middleware('permission:accounting.write')  ->name('edit');
            Route::put('/{incoterm}',        [\App\Http\Controllers\Accounting\AccountingIncotermController::class, 'write'])   ->middleware('permission:accounting.write')  ->name('update');
            Route::delete('/{incoterm}',      [\App\Http\Controllers\Accounting\AccountingIncotermController::class, 'unlink'])    ->middleware('permission:accounting.unlink') ->name('delete');
            Route::post('/{incoterm}/comment',[\App\Http\Controllers\Accounting\AccountingIncotermController::class, 'addComment'])->middleware('permission:accounting.write')  ->name('comment');
        });

        // Tax Groups
        Route::prefix('tax-groups')->name('tax-groups.')->group(function () {
            Route::get('/',              [\App\Http\Controllers\Accounting\AccountingTaxGroupController::class, 'read'])    ->middleware('permission:accounting.read')   ->name('index');
            Route::get('/create',        [\App\Http\Controllers\Accounting\AccountingTaxGroupController::class, 'create'])  ->middleware('permission:accounting.create') ->name('create');
            Route::post('/',             [\App\Http\Controllers\Accounting\AccountingTaxGroupController::class, 'store'])   ->middleware('permission:accounting.create') ->name('store');
            Route::get('/{taxGroup}',        [\App\Http\Controllers\Accounting\AccountingTaxGroupController::class, 'show'])    ->middleware('permission:accounting.read')   ->name('show');
            Route::get('/{taxGroup}/edit',   [\App\Http\Controllers\Accounting\AccountingTaxGroupController::class, 'edit'])    ->middleware('permission:accounting.write')  ->name('edit');
            Route::put('/{taxGroup}',        [\App\Http\Controllers\Accounting\AccountingTaxGroupController::class, 'write'])   ->middleware('permission:accounting.write')  ->name('update');
            Route::delete('/{taxGroup}',      [\App\Http\Controllers\Accounting\AccountingTaxGroupController::class, 'unlink'])    ->middleware('permission:accounting.unlink') ->name('delete');
            Route::post('/{taxGroup}/comment',[\App\Http\Controllers\Accounting\AccountingTaxGroupController::class, 'addComment'])->middleware('permission:accounting.write')  ->name('comment');
        });

        // Account Groups
        Route::prefix('account-groups')->name('account-groups.')->group(function () {
            Route::get('/',                   [\App\Http\Controllers\Accounting\AccountingAccountGroupController::class, 'read'])    ->middleware('permission:accounting.read')   ->name('index');
            Route::get('/create',             [\App\Http\Controllers\Accounting\AccountingAccountGroupController::class, 'create'])  ->middleware('permission:accounting.create') ->name('create');
            Route::post('/',                  [\App\Http\Controllers\Accounting\AccountingAccountGroupController::class, 'store'])   ->middleware('permission:accounting.create') ->name('store');
            Route::get('/{accountGroup}',        [\App\Http\Controllers\Accounting\AccountingAccountGroupController::class, 'show'])    ->middleware('permission:accounting.read')   ->name('show');
            Route::get('/{accountGroup}/edit',   [\App\Http\Controllers\Accounting\AccountingAccountGroupController::class, 'edit'])    ->middleware('permission:accounting.write')  ->name('edit');
            Route::put('/{accountGroup}',        [\App\Http\Controllers\Accounting\AccountingAccountGroupController::class, 'write'])   ->middleware('permission:accounting.write')  ->name('update');
            Route::delete('/{accountGroup}',      [\App\Http\Controllers\Accounting\AccountingAccountGroupController::class, 'unlink'])    ->middleware('permission:accounting.unlink') ->name('delete');
            Route::post('/{accountGroup}/comment',[\App\Http\Controllers\Accounting\AccountingAccountGroupController::class, 'addComment'])->middleware('permission:accounting.write')  ->name('comment');
        });

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/general-ledger',    [\App\Http\Controllers\Accounting\AccountingReportController::class, 'generalLedger'])    ->middleware('permission:accounting.read')->name('general-ledger');
            Route::get('/trial-balance',     [\App\Http\Controllers\Accounting\AccountingReportController::class, 'trialBalance'])     ->middleware('permission:accounting.read')->name('trial-balance');
            Route::get('/profit-and-loss',   [\App\Http\Controllers\Accounting\AccountingReportController::class, 'profitAndLoss'])    ->middleware('permission:accounting.read')->name('profit-and-loss');
            Route::get('/balance-sheet',     [\App\Http\Controllers\Accounting\AccountingReportController::class, 'balanceSheet'])     ->middleware('permission:accounting.read')->name('balance-sheet');
            Route::get('/cash-flow',         [\App\Http\Controllers\Accounting\AccountingReportController::class, 'cashFlow'])         ->middleware('permission:accounting.read')->name('cash-flow');
            Route::get('/tax-report',        [\App\Http\Controllers\Accounting\AccountingReportController::class, 'taxReport'])        ->middleware('permission:accounting.read')->name('tax-report');
            Route::get('/partner-ledger',    [\App\Http\Controllers\Accounting\AccountingReportController::class, 'partnerLedger'])    ->middleware('permission:accounting.read')->name('partner-ledger');
            Route::get('/aged-receivable',   [\App\Http\Controllers\Accounting\AccountingReportController::class, 'agedReceivable'])   ->middleware('permission:accounting.read')->name('aged-receivable');
            Route::get('/aged-payable',      [\App\Http\Controllers\Accounting\AccountingReportController::class, 'agedPayable'])      ->middleware('permission:accounting.read')->name('aged-payable');
            Route::get('/journal-audit',     [\App\Http\Controllers\Accounting\AccountingReportController::class, 'journalAudit'])     ->middleware('permission:accounting.read')->name('journal-audit');
            Route::get('/bank-reconciliation',[\App\Http\Controllers\Accounting\AccountingReportController::class, 'bankReconciliation'])->middleware('permission:accounting.read')->name('bank-reconciliation');
            Route::get('/executive-summary', [\App\Http\Controllers\Accounting\AccountingReportController::class, 'executiveSummary'])  ->middleware('permission:accounting.read')->name('executive-summary');

            // Unified export endpoint — gated by accounting.export
            Route::get('/{report}/export', \App\Http\Controllers\Accounting\AccountingReportExportController::class)
                ->middleware('permission:accounting.export')->name('export');
        });

        // Accounting Settings (lock dates)
        Route::get('/settings',                [\App\Http\Controllers\Accounting\AccountingSettingsController::class, 'read'])
            ->middleware('permission:accounting.read')->name('settings');
        Route::put('/settings/{company}',      [\App\Http\Controllers\Accounting\AccountingSettingsController::class, 'write'])
            ->middleware('permission:accounting.lock')->name('settings.update');

        // Audit log
        Route::get('/audit', [\App\Http\Controllers\Accounting\AccountingAuditController::class, 'read'])
            ->middleware('permission:accounting.read')->name('audit');
    });

    /*
    |--------------------------------------------------------------------------
    | Inventory module
    |--------------------------------------------------------------------------
    */
    Route::prefix('inventory')->name('inventory.')->group(function () {

        // Dashboard
        Route::get('/', [\App\Http\Controllers\Inventory\InventoryDashboardController::class, 'index'])
            ->middleware('permission:inventory.read')->name('dashboard');

        // Products
        Route::prefix('products')->name('products.')->group(function () {
            Route::get('/',                    [\App\Http\Controllers\Inventory\ProductController::class, 'read'])       ->middleware('permission:inventory.read')   ->name('index');
            Route::get('/create',              [\App\Http\Controllers\Inventory\ProductController::class, 'create'])     ->middleware('permission:inventory.create') ->name('create');
            Route::get('/uom-info',            [\App\Http\Controllers\Inventory\ProductController::class, 'uomInfo'])    ->middleware('permission:inventory.read')   ->name('uom-info');
            Route::post('/',                   [\App\Http\Controllers\Inventory\ProductController::class, 'store'])      ->middleware('permission:inventory.create') ->name('store');
            Route::get('/{product}',           [\App\Http\Controllers\Inventory\ProductController::class, 'show'])       ->middleware('permission:inventory.read')   ->name('show');
            Route::get('/{product}/edit',      [\App\Http\Controllers\Inventory\ProductController::class, 'edit'])       ->middleware('permission:inventory.write')  ->name('edit');
            Route::put('/{product}',           [\App\Http\Controllers\Inventory\ProductController::class, 'write'])      ->middleware('permission:inventory.write')  ->name('update');
            Route::patch('/{product}/archive', [\App\Http\Controllers\Inventory\ProductController::class, 'archive'])   ->middleware('permission:inventory.write')  ->name('archive');
            Route::patch('/{product}/unarchive',[\App\Http\Controllers\Inventory\ProductController::class, 'unarchive'])->middleware('permission:inventory.write')  ->name('unarchive');
            Route::delete('/{product}',        [\App\Http\Controllers\Inventory\ProductController::class, 'unlink'])    ->middleware('permission:inventory.unlink') ->name('delete');
            Route::post('/{product}/comment',  [\App\Http\Controllers\Inventory\ProductController::class, 'addComment'])->middleware('permission:inventory.write')  ->name('comment');
        });

        // Transfers (Pickings)
        Route::prefix('transfers')->name('transfers.')->group(function () {
            Route::get('/',                          [\App\Http\Controllers\Inventory\PickingController::class, 'read'])             ->middleware('permission:inventory.read')   ->name('index');
            Route::get('/create',                    [\App\Http\Controllers\Inventory\PickingController::class, 'create'])           ->middleware('permission:inventory.create') ->name('create');
            Route::get('/new-move-row',              [\App\Http\Controllers\Inventory\PickingController::class, 'newMoveRow'])       ->middleware('permission:inventory.create') ->name('new-move-row');
            Route::post('/',                         [\App\Http\Controllers\Inventory\PickingController::class, 'store'])            ->middleware('permission:inventory.create') ->name('store');
            Route::get('/{picking}',                 [\App\Http\Controllers\Inventory\PickingController::class, 'show'])             ->middleware('permission:inventory.read')   ->name('show');
            Route::get('/{picking}/edit',            [\App\Http\Controllers\Inventory\PickingController::class, 'edit'])             ->middleware('permission:inventory.write')  ->name('edit');
            Route::put('/{picking}',                 [\App\Http\Controllers\Inventory\PickingController::class, 'write'])            ->middleware('permission:inventory.write')  ->name('update');
            Route::post('/{picking}/confirm',        [\App\Http\Controllers\Inventory\PickingController::class, 'confirm'])          ->middleware('permission:inventory.write')  ->name('confirm');
            Route::post('/{picking}/check-availability', [\App\Http\Controllers\Inventory\PickingController::class, 'checkAvailability'])->middleware('permission:inventory.write')->name('check-availability');
            Route::post('/{picking}/validate',       [\App\Http\Controllers\Inventory\PickingController::class, 'validate'])         ->middleware('permission:inventory.write')  ->name('validate');
            Route::post('/{picking}/cancel',         [\App\Http\Controllers\Inventory\PickingController::class, 'cancel'])           ->middleware('permission:inventory.write')  ->name('cancel');
            Route::post('/{picking}/return',         [\App\Http\Controllers\Inventory\PickingController::class, 'returnPicking'])    ->middleware('permission:inventory.write')  ->name('return');
            Route::delete('/{picking}',              [\App\Http\Controllers\Inventory\PickingController::class, 'unlink'])           ->middleware('permission:inventory.unlink') ->name('delete');
            Route::post('/{picking}/comment',        [\App\Http\Controllers\Inventory\PickingController::class, 'addComment'])       ->middleware('permission:inventory.write')  ->name('comment');
        });

        // Receipts (incoming pickings)
        Route::prefix('receipts')->name('receipts.')->group(function () {
            Route::get('/',       [\App\Http\Controllers\Inventory\PickingController::class, 'readReceipts'])   ->middleware('permission:inventory.read')   ->name('index');
            Route::get('/create', [\App\Http\Controllers\Inventory\PickingController::class, 'createReceipt'])  ->middleware('permission:inventory.create') ->name('create');
        });

        // Deliveries (outgoing pickings)
        Route::prefix('deliveries')->name('deliveries.')->group(function () {
            Route::get('/',       [\App\Http\Controllers\Inventory\PickingController::class, 'readDeliveries'])  ->middleware('permission:inventory.read')   ->name('index');
            Route::get('/create', [\App\Http\Controllers\Inventory\PickingController::class, 'createDelivery']) ->middleware('permission:inventory.create') ->name('create');
        });

        // Internal Transfers
        Route::prefix('internal-transfers')->name('internal-transfers.')->group(function () {
            Route::get('/',       [\App\Http\Controllers\Inventory\PickingController::class, 'readInternal'])   ->middleware('permission:inventory.read')   ->name('index');
            Route::get('/create', [\App\Http\Controllers\Inventory\PickingController::class, 'createInternal']) ->middleware('permission:inventory.create') ->name('create');
        });

        // Lots / Serial Numbers
        Route::prefix('lots')->name('lots.')->group(function () {
            Route::get('/',               [\App\Http\Controllers\Inventory\LotController::class, 'read'])       ->middleware('permission:inventory.read')   ->name('index');
            Route::get('/create',         [\App\Http\Controllers\Inventory\LotController::class, 'create'])     ->middleware('permission:inventory.create') ->name('create');
            Route::post('/',              [\App\Http\Controllers\Inventory\LotController::class, 'store'])      ->middleware('permission:inventory.create') ->name('store');
            Route::get('/{lot}',          [\App\Http\Controllers\Inventory\LotController::class, 'show'])       ->middleware('permission:inventory.read')   ->name('show');
            Route::get('/{lot}/edit',     [\App\Http\Controllers\Inventory\LotController::class, 'edit'])       ->middleware('permission:inventory.write')  ->name('edit');
            Route::put('/{lot}',          [\App\Http\Controllers\Inventory\LotController::class, 'write'])      ->middleware('permission:inventory.write')  ->name('update');
            Route::delete('/{lot}',       [\App\Http\Controllers\Inventory\LotController::class, 'unlink'])     ->middleware('permission:inventory.unlink') ->name('delete');
            Route::post('/{lot}/comment', [\App\Http\Controllers\Inventory\LotController::class, 'addComment']) ->middleware('permission:inventory.write')  ->name('comment');
        });

        // Scrap
        Route::prefix('scrap')->name('scrap.')->group(function () {
            Route::get('/',                    [\App\Http\Controllers\Inventory\ScrapOrderController::class, 'read'])         ->middleware('permission:inventory.read')   ->name('index');
            Route::get('/create',              [\App\Http\Controllers\Inventory\ScrapOrderController::class, 'create'])       ->middleware('permission:inventory.create') ->name('create');
            Route::post('/',                   [\App\Http\Controllers\Inventory\ScrapOrderController::class, 'store'])        ->middleware('permission:inventory.create') ->name('store');
            Route::get('/{scrapOrder}',        [\App\Http\Controllers\Inventory\ScrapOrderController::class, 'show'])         ->middleware('permission:inventory.read')   ->name('show');
            Route::post('/{scrapOrder}/validate', [\App\Http\Controllers\Inventory\ScrapOrderController::class, 'validateScrap'])->middleware('permission:inventory.write')->name('validate');
            Route::delete('/{scrapOrder}',     [\App\Http\Controllers\Inventory\ScrapOrderController::class, 'unlink'])       ->middleware('permission:inventory.unlink') ->name('delete');
            Route::post('/{scrapOrder}/comment', [\App\Http\Controllers\Inventory\ScrapOrderController::class, 'addComment']) ->middleware('permission:inventory.write')  ->name('comment');
        });

        // Replenishment (Reorder Rules)
        Route::prefix('replenishment')->name('replenishment.')->group(function () {
            Route::get('/',               [\App\Http\Controllers\Inventory\ReorderRuleController::class, 'read'])        ->middleware('permission:inventory.read')   ->name('index');
            Route::get('/create',         [\App\Http\Controllers\Inventory\ReorderRuleController::class, 'create'])      ->middleware('permission:inventory.create') ->name('create');
            Route::post('/',              [\App\Http\Controllers\Inventory\ReorderRuleController::class, 'store'])       ->middleware('permission:inventory.create') ->name('store');
            Route::get('/{reorderRule}/edit',    [\App\Http\Controllers\Inventory\ReorderRuleController::class, 'edit'])        ->middleware('permission:inventory.write')  ->name('edit');
            Route::put('/{reorderRule}',         [\App\Http\Controllers\Inventory\ReorderRuleController::class, 'write'])       ->middleware('permission:inventory.write')  ->name('update');
            Route::post('/{reorderRule}/replenish', [\App\Http\Controllers\Inventory\ReorderRuleController::class, 'replenish'])->middleware('permission:inventory.write')  ->name('replenish');
            Route::delete('/{reorderRule}',      [\App\Http\Controllers\Inventory\ReorderRuleController::class, 'unlink'])      ->middleware('permission:inventory.unlink') ->name('delete');
        });

        // Physical Inventory (Adjustments)
        Route::prefix('adjustments')->name('adjustments.')->group(function () {
            Route::get('/',                             [\App\Http\Controllers\Inventory\InventoryAdjustmentController::class, 'read'])             ->middleware('permission:inventory.read')  ->name('index');
            Route::get('/create',                       [\App\Http\Controllers\Inventory\InventoryAdjustmentController::class, 'create'])           ->middleware('permission:inventory.create') ->name('create');
            Route::post('/',                            [\App\Http\Controllers\Inventory\InventoryAdjustmentController::class, 'store'])            ->middleware('permission:inventory.create') ->name('store');
            Route::get('/{inventoryAdjustment}',        [\App\Http\Controllers\Inventory\InventoryAdjustmentController::class, 'show'])             ->middleware('permission:inventory.read')  ->name('show');
            Route::post('/{inventoryAdjustment}/start', [\App\Http\Controllers\Inventory\InventoryAdjustmentController::class, 'startCount'])       ->middleware('permission:inventory.write') ->name('start');
            Route::post('/{inventoryAdjustment}/lines/{line}', [\App\Http\Controllers\Inventory\InventoryAdjustmentController::class, 'updateLine'])->middleware('permission:inventory.write') ->name('update-line');
            Route::post('/{inventoryAdjustment}/validate', [\App\Http\Controllers\Inventory\InventoryAdjustmentController::class, 'validateAdjustment'])->middleware('permission:inventory.write')->name('validate');
            Route::delete('/{inventoryAdjustment}',     [\App\Http\Controllers\Inventory\InventoryAdjustmentController::class, 'unlink'])           ->middleware('permission:inventory.unlink')->name('delete');
            Route::post('/{inventoryAdjustment}/comment', [\App\Http\Controllers\Inventory\InventoryAdjustmentController::class, 'addComment'])     ->middleware('permission:inventory.write') ->name('comment');
        });

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/stock', [\App\Http\Controllers\Inventory\QuantController::class, 'read'])->middleware('permission:inventory.read')->name('stock');
        });

        // Configuration
        Route::prefix('configuration')->name('config.')->group(function () {

            // Product Categories
            Route::prefix('product-categories')->name('product-categories.')->group(function () {
                Route::get('/',                      [\App\Http\Controllers\Inventory\Configuration\ProductCategoryController::class, 'read'])       ->middleware('permission:inventory.read')   ->name('index');
                Route::get('/create',                [\App\Http\Controllers\Inventory\Configuration\ProductCategoryController::class, 'create'])     ->middleware('permission:inventory.config') ->name('create');
                Route::post('/',                     [\App\Http\Controllers\Inventory\Configuration\ProductCategoryController::class, 'store'])      ->middleware('permission:inventory.config') ->name('store');
                Route::get('/{productCategory}',     [\App\Http\Controllers\Inventory\Configuration\ProductCategoryController::class, 'show'])       ->middleware('permission:inventory.read')   ->name('show');
                Route::get('/{productCategory}/edit',[\App\Http\Controllers\Inventory\Configuration\ProductCategoryController::class, 'edit'])       ->middleware('permission:inventory.config') ->name('edit');
                Route::put('/{productCategory}',     [\App\Http\Controllers\Inventory\Configuration\ProductCategoryController::class, 'write'])      ->middleware('permission:inventory.config') ->name('update');
                Route::delete('/{productCategory}',  [\App\Http\Controllers\Inventory\Configuration\ProductCategoryController::class, 'unlink'])     ->middleware('permission:inventory.config') ->name('delete');
            });

            // Units of Measure
            Route::prefix('uoms')->name('uoms.')->group(function () {
                Route::get('/',          [\App\Http\Controllers\Inventory\Configuration\UomController::class, 'read'])   ->middleware('permission:inventory.read')   ->name('index');
                Route::get('/create',    [\App\Http\Controllers\Inventory\Configuration\UomController::class, 'create']) ->middleware('permission:inventory.config') ->name('create');
                Route::post('/',         [\App\Http\Controllers\Inventory\Configuration\UomController::class, 'store'])  ->middleware('permission:inventory.config') ->name('store');
                Route::get('/{uom}',     [\App\Http\Controllers\Inventory\Configuration\UomController::class, 'show'])   ->middleware('permission:inventory.read')   ->name('show');
                Route::get('/{uom}/edit',[\App\Http\Controllers\Inventory\Configuration\UomController::class, 'edit'])   ->middleware('permission:inventory.config') ->name('edit');
                Route::put('/{uom}',     [\App\Http\Controllers\Inventory\Configuration\UomController::class, 'write'])  ->middleware('permission:inventory.config') ->name('update');
                Route::delete('/{uom}',  [\App\Http\Controllers\Inventory\Configuration\UomController::class, 'unlink']) ->middleware('permission:inventory.config') ->name('delete');
            });

            // Warehouses
            Route::prefix('warehouses')->name('warehouses.')->group(function () {
                Route::get('/',                       [\App\Http\Controllers\Inventory\Configuration\WarehouseController::class, 'read'])       ->middleware('permission:inventory.config') ->name('index');
                Route::get('/create',                 [\App\Http\Controllers\Inventory\Configuration\WarehouseController::class, 'create'])     ->middleware('permission:inventory.config') ->name('create');
                Route::post('/',                      [\App\Http\Controllers\Inventory\Configuration\WarehouseController::class, 'store'])      ->middleware('permission:inventory.config') ->name('store');
                Route::get('/{warehouse}',            [\App\Http\Controllers\Inventory\Configuration\WarehouseController::class, 'show'])       ->middleware('permission:inventory.config') ->name('show');
                Route::get('/{warehouse}/edit',       [\App\Http\Controllers\Inventory\Configuration\WarehouseController::class, 'edit'])       ->middleware('permission:inventory.config') ->name('edit');
                Route::put('/{warehouse}',            [\App\Http\Controllers\Inventory\Configuration\WarehouseController::class, 'write'])      ->middleware('permission:inventory.config') ->name('update');
                Route::patch('/{warehouse}/archive',  [\App\Http\Controllers\Inventory\Configuration\WarehouseController::class, 'archive'])    ->middleware('permission:inventory.config') ->name('archive');
                Route::patch('/{warehouse}/unarchive',[\App\Http\Controllers\Inventory\Configuration\WarehouseController::class, 'unarchive'])  ->middleware('permission:inventory.config') ->name('unarchive');
                Route::delete('/{warehouse}',         [\App\Http\Controllers\Inventory\Configuration\WarehouseController::class, 'unlink'])     ->middleware('permission:inventory.config') ->name('delete');
                Route::post('/{warehouse}/comment',   [\App\Http\Controllers\Inventory\Configuration\WarehouseController::class, 'addComment']) ->middleware('permission:inventory.config') ->name('comment');
            });

            // Locations
            Route::prefix('locations')->name('locations.')->group(function () {
                Route::get('/',                      [\App\Http\Controllers\Inventory\Configuration\LocationController::class, 'read'])       ->middleware('permission:inventory.read')   ->name('index');
                Route::get('/create',                [\App\Http\Controllers\Inventory\Configuration\LocationController::class, 'create'])     ->middleware('permission:inventory.config') ->name('create');
                Route::post('/',                     [\App\Http\Controllers\Inventory\Configuration\LocationController::class, 'store'])      ->middleware('permission:inventory.config') ->name('store');
                Route::get('/{location}',            [\App\Http\Controllers\Inventory\Configuration\LocationController::class, 'show'])       ->middleware('permission:inventory.read')   ->name('show');
                Route::get('/{location}/edit',       [\App\Http\Controllers\Inventory\Configuration\LocationController::class, 'edit'])       ->middleware('permission:inventory.config') ->name('edit');
                Route::put('/{location}',            [\App\Http\Controllers\Inventory\Configuration\LocationController::class, 'write'])      ->middleware('permission:inventory.config') ->name('update');
                Route::patch('/{location}/archive',  [\App\Http\Controllers\Inventory\Configuration\LocationController::class, 'archive'])    ->middleware('permission:inventory.config') ->name('archive');
                Route::patch('/{location}/unarchive',[\App\Http\Controllers\Inventory\Configuration\LocationController::class, 'unarchive'])  ->middleware('permission:inventory.config') ->name('unarchive');
                Route::delete('/{location}',         [\App\Http\Controllers\Inventory\Configuration\LocationController::class, 'unlink'])     ->middleware('permission:inventory.config') ->name('delete');
                Route::post('/{location}/comment',   [\App\Http\Controllers\Inventory\Configuration\LocationController::class, 'addComment']) ->middleware('permission:inventory.config') ->name('comment');
            });

            // Operation Types
            Route::prefix('operation-types')->name('operation-types.')->group(function () {
                Route::get('/',                          [\App\Http\Controllers\Inventory\Configuration\OperationTypeController::class, 'read'])       ->middleware('permission:inventory.config') ->name('index');
                Route::get('/create',                    [\App\Http\Controllers\Inventory\Configuration\OperationTypeController::class, 'create'])     ->middleware('permission:inventory.config') ->name('create');
                Route::post('/',                         [\App\Http\Controllers\Inventory\Configuration\OperationTypeController::class, 'store'])      ->middleware('permission:inventory.config') ->name('store');
                Route::get('/{operationType}',           [\App\Http\Controllers\Inventory\Configuration\OperationTypeController::class, 'show'])       ->middleware('permission:inventory.config') ->name('show');
                Route::get('/{operationType}/edit',      [\App\Http\Controllers\Inventory\Configuration\OperationTypeController::class, 'edit'])       ->middleware('permission:inventory.config') ->name('edit');
                Route::put('/{operationType}',           [\App\Http\Controllers\Inventory\Configuration\OperationTypeController::class, 'write'])      ->middleware('permission:inventory.config') ->name('update');
                Route::patch('/{operationType}/archive', [\App\Http\Controllers\Inventory\Configuration\OperationTypeController::class, 'archive'])    ->middleware('permission:inventory.config') ->name('archive');
                Route::patch('/{operationType}/unarchive',[\App\Http\Controllers\Inventory\Configuration\OperationTypeController::class, 'unarchive']) ->middleware('permission:inventory.config') ->name('unarchive');
                Route::delete('/{operationType}',        [\App\Http\Controllers\Inventory\Configuration\OperationTypeController::class, 'unlink'])     ->middleware('permission:inventory.config') ->name('delete');
            });

            // Routes
            Route::prefix('routes')->name('routes.')->group(function () {
                Route::get('/',                 [\App\Http\Controllers\Inventory\Configuration\RouteController::class, 'read'])       ->middleware('permission:inventory.config') ->name('index');
                Route::get('/create',           [\App\Http\Controllers\Inventory\Configuration\RouteController::class, 'create'])     ->middleware('permission:inventory.config') ->name('create');
                Route::post('/',                [\App\Http\Controllers\Inventory\Configuration\RouteController::class, 'store'])      ->middleware('permission:inventory.config') ->name('store');
                Route::get('/new-rule-row',     [\App\Http\Controllers\Inventory\Configuration\RouteController::class, 'newRuleRow'])->middleware('permission:inventory.config') ->name('new-rule-row');
                Route::get('/{route}',          [\App\Http\Controllers\Inventory\Configuration\RouteController::class, 'show'])       ->middleware('permission:inventory.config') ->name('show');
                Route::get('/{route}/edit',     [\App\Http\Controllers\Inventory\Configuration\RouteController::class, 'edit'])       ->middleware('permission:inventory.config') ->name('edit');
                Route::put('/{route}',          [\App\Http\Controllers\Inventory\Configuration\RouteController::class, 'write'])      ->middleware('permission:inventory.config') ->name('update');
                Route::patch('/{route}/archive',[\App\Http\Controllers\Inventory\Configuration\RouteController::class, 'archive'])    ->middleware('permission:inventory.config') ->name('archive');
                Route::patch('/{route}/unarchive',[\App\Http\Controllers\Inventory\Configuration\RouteController::class, 'unarchive'])->middleware('permission:inventory.config')->name('unarchive');
                Route::delete('/{route}',       [\App\Http\Controllers\Inventory\Configuration\RouteController::class, 'unlink'])     ->middleware('permission:inventory.config') ->name('delete');
            });

            // Putaway Rules
            Route::prefix('putaway-rules')->name('putaway-rules.')->group(function () {
                Route::get('/',               [\App\Http\Controllers\Inventory\Configuration\PutawayRuleController::class, 'read'])   ->middleware('permission:inventory.config') ->name('index');
                Route::get('/create',         [\App\Http\Controllers\Inventory\Configuration\PutawayRuleController::class, 'create']) ->middleware('permission:inventory.config') ->name('create');
                Route::post('/',              [\App\Http\Controllers\Inventory\Configuration\PutawayRuleController::class, 'store'])  ->middleware('permission:inventory.config') ->name('store');
                Route::get('/{putawayRule}/edit', [\App\Http\Controllers\Inventory\Configuration\PutawayRuleController::class, 'edit'])->middleware('permission:inventory.config')->name('edit');
                Route::put('/{putawayRule}',  [\App\Http\Controllers\Inventory\Configuration\PutawayRuleController::class, 'write'])  ->middleware('permission:inventory.config') ->name('update');
                Route::delete('/{putawayRule}',[\App\Http\Controllers\Inventory\Configuration\PutawayRuleController::class, 'unlink'])->middleware('permission:inventory.config') ->name('delete');
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
});
