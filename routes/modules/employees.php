<?php

use App\Http\Controllers\Employees\AttendanceController;
use App\Http\Controllers\Employees\BadgeController;
use App\Http\Controllers\Employees\ChallengeController;
use App\Http\Controllers\Employees\ContractController;
use App\Http\Controllers\Employees\DepartmentController as EmployeeDepartmentController;
use App\Http\Controllers\Employees\DepartureReasonController;
use App\Http\Controllers\Employees\EmployeeAppreciationController;
use App\Http\Controllers\Employees\EmployeeBonusController;
use App\Http\Controllers\Employees\EmployeeCategoryController;
use App\Http\Controllers\Employees\EmployeeCertificateController;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\Employees\EmployeeDocumentController;
use App\Http\Controllers\Employees\EmployeeJobGradeController;
use App\Http\Controllers\Employees\EmployeePositionController;
use App\Http\Controllers\Employees\EmployeeRequestController;
use App\Http\Controllers\Employees\EmployeeRewardController;
use App\Http\Controllers\Employees\EmployeeSanctionController;
use App\Http\Controllers\Employees\EmploymentTypeController;
use App\Http\Controllers\Employees\GoalController;
use App\Http\Controllers\Employees\JobController;
use App\Http\Controllers\Employees\PlannedScheduleController;
use App\Http\Controllers\Employees\RequestBalanceConfigController;
use App\Http\Controllers\Employees\RequestSubtypeController;
use App\Http\Controllers\Employees\ResourceCalendarController;
use App\Http\Controllers\Employees\ResumeLineTypeController;
use App\Http\Controllers\Employees\SkillTypeController;
use App\Http\Controllers\Employees\WorkLocationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Employees module
|--------------------------------------------------------------------------
| Required from routes/web.php inside the auth middleware group.
|
| Sub-routes (attendances, departments, jobs, work-locations, schedules,
| categories, departure-reasons, skill-types, resume-line-types,
| employment-types, badges, challenges, goals, positions, documents,
| certificates, bonuses, appreciations, sanctions, rewards, job-grades,
| my-requests, requests, request-subtypes, request-balance-config,
| {employee}/planned-schedule) MUST come before /{employee} to avoid
| route-model-binding conflicts.
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
