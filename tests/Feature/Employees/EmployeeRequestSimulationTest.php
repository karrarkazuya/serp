<?php

namespace Tests\Feature\Employees;

use App\Models\Employees\Attendance;
use App\Models\Employees\Department;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeRequest;
use App\Models\Employees\RequestBalanceConfig;
use App\Models\Employees\RequestSubtype;
use App\Models\Employees\ResourceCalendar;
use App\Models\Employees\ResourceCalendarAttendance;
use App\Models\Settings\Company;
use App\Models\User;
use App\Services\Employees\BalanceService;
use App\Services\Employees\EmployeeRequestService;
use Carbon\Carbon;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Tests\TestCase;

/**
 * Simulation suite for the employee-requests subsystem.
 *
 * Each test builds the minimal world it needs (company, calendar with Sun-Thu
 * working hours, employee + manager + balance + subtypes), drives the service
 * through a scenario, and asserts both the EmployeeRequest record AND its
 * downstream effects (attendance rows, balance deductions, notifications).
 *
 * Coverage matrix:
 *   1.  validation: overtime overlapping working hours -> rejected
 *   2.  validation: overtime on day-off allowed
 *   3.  validation: time off fully within working hours allowed
 *   4.  validation: time off spilling outside working hours rejected
 *   5.  validation: time off on a full day-off rejected
 *   6.  leave spanning a day-off accepted; full balance deducted
 *   7.  approval flow: manager + HR both approve -> approved + side-effects
 *   8.  approval flow: manager rejects -> rejected, no side effects
 *   9.  approval flow: HR override approves alone -> approved
 *  10.  approval flow: HR rejects after manager approved -> rejected
 *  11.  balance: insufficient leave balance blocks HR approval
 *  12.  attendance side effect: approved time off reduces expected_hours + tags request_id
 *  13.  attendance side effect: approved overtime adds approved_overtime_hours + tags request_id
 *  14.  balance cron: leave accumulative & capped at max
 *  15.  balance cron: time off resets every month
 *  16.  cross-company isolation in viewing a request
 *  17.  overlap with existing pending request rejected at submit
 *  18.  locked request (approved or rejected) cannot be re-decided
 */
class EmployeeRequestSimulationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private ResourceCalendar $calendar;
    private Employee $employee;
    private Employee $manager;
    private User $managerUser;
    private User $hrUser;
    private EmployeeRequestService $svc;
    private BalanceService $balanceSvc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);

        $this->company = Company::create(['name' => 'Acme', 'currency' => 'USD']);
        $this->managerUser = User::create([
            'name' => 'Manager', 'email' => 'mgr@example.com', 'password' => bcrypt('x'), 'active' => true,
        ]);
        $this->hrUser = User::create([
            'name' => 'HR', 'email' => 'hr@example.com', 'password' => bcrypt('x'), 'active' => true,
        ]);
        // Grant HR override permission.
        $hrPerm = \App\Models\Security\Permission::where('key', 'attendance.hr_approve')->first();
        $hrRole = \App\Models\Security\Role::create([
            'name' => 'HR', 'key' => 'hr_test', 'description' => 't', 'active' => true,
        ]);
        $hrRole->permissions()->attach($hrPerm->id);
        $this->hrUser->roles()->attach($hrRole->id);

        // Standard Sun-Thu schedule, 09:00-17:00.
        $this->calendar = ResourceCalendar::create([
            'name' => 'Standard 40', 'timezone' => 'UTC', 'hours_per_day' => 8,
            'company_id' => $this->company->id, 'active' => true,
        ]);
        foreach ([1, 2, 3, 4, 5] as $dow) { // Sun=1 .. Thu=5 (system convention 0=Sat)
            ResourceCalendarAttendance::create([
                'calendar_id' => $this->calendar->id, 'day_of_week' => $dow,
                'hour_from' => 9.0, 'hour_to' => 17.0, 'day_period' => 'morning', 'sequence' => 0,
            ]);
        }

        $dept = Department::create(['name' => 'D', 'company_id' => $this->company->id, 'active' => true]);
        $this->manager = Employee::create([
            'name' => 'Manager Emp', 'employee_code' => 'M1', 'company_id' => $this->company->id,
            'department_id' => $dept->id, 'user_id' => $this->managerUser->id,
            'resource_calendar_id' => $this->calendar->id, 'employment_status' => 'active',
            'hire_date' => '2024-01-01', 'active' => true,
        ]);
        $this->employee = Employee::create([
            'name' => 'Employee', 'employee_code' => 'E1', 'company_id' => $this->company->id,
            'department_id' => $dept->id, 'resource_calendar_id' => $this->calendar->id,
            'attendance_manager_id' => $this->manager->id, 'employment_status' => 'active',
            'hire_date' => '2024-01-01', 'active' => true,
        ]);

        // Per-company balance config + employee balance.
        RequestBalanceConfig::create([
            'company_id' => $this->company->id,
            'leave_days_per_month' => 2, 'leave_days_max' => 30, 'time_off_hours_per_month' => 8,
        ]);

        $this->svc        = app(EmployeeRequestService::class);
        $this->balanceSvc = app(BalanceService::class);
        $bal = $this->balanceSvc->getOrCreate($this->employee);
        $bal->update(['leave_days_balance' => 20, 'time_off_hours_balance' => 16]);

        Auth::login($this->hrUser);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeSubtype(array $overrides = []): RequestSubtype
    {
        return RequestSubtype::create(array_merge([
            'name' => 'Default', 'type' => RequestSubtype::TYPE_LEAVE,
            'cuts_salary' => false, 'cuts_balance' => true, 'factor' => 1.0,
            'requires_title' => false, 'requires_description' => false, 'requires_attachment' => false,
            'active' => true,
        ], $overrides));
    }

    /** Pick the next Sunday on/after $from. Sun=1 in system convention; Carbon's dayOfWeek Sun=0. */
    private function nextWorkingSunday(): Carbon
    {
        $d = Carbon::today();
        while ($d->dayOfWeek !== 0) $d->addDay(); // Carbon Sunday = 0
        return $d;
    }
    /** Pick the next Friday (off-day) on/after today. */
    private function nextDayOff(): Carbon
    {
        $d = Carbon::today();
        while ($d->dayOfWeek !== 5) $d->addDay(); // Carbon Friday = 5
        return $d;
    }

    private function createRequest(string $type, RequestSubtype $st, Carbon $start, Carbon $end, array $extra = []): EmployeeRequest
    {
        return $this->svc->create(array_merge([
            'employee_id' => $this->employee->id, 'subtype_id' => $st->id, 'type' => $type,
            'start_at' => $start->toDateTimeString(), 'end_at' => $end->toDateTimeString(),
        ], $extra));
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /** 1. Overtime overlapping working hours is rejected. */
    public function test_overtime_overlapping_working_hours_is_rejected(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_OVERTIME, 'cuts_balance' => false]);
        $sun = $this->nextWorkingSunday();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Overtime cannot overlap working hours/');

        $this->createRequest('overtime', $st, $sun->copy()->setTime(10, 0), $sun->copy()->setTime(12, 0));
    }

    /** 2. Overtime on a day-off (Friday) is allowed. */
    public function test_overtime_on_day_off_allowed(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_OVERTIME, 'cuts_balance' => false]);
        $fri = $this->nextDayOff();

        $req = $this->createRequest('overtime', $st, $fri->copy()->setTime(9, 0), $fri->copy()->setTime(13, 0));
        $this->assertEquals(EmployeeRequest::STATE_PENDING, $req->state);
        $this->assertEquals(4.0, (float) $req->duration_hours);
    }

    /** 3. Time off fully within working hours on a working day is allowed. */
    public function test_time_off_inside_working_hours_allowed(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_TIME_OFF]);
        $sun = $this->nextWorkingSunday();

        $req = $this->createRequest('time_off', $st, $sun->copy()->setTime(10, 0), $sun->copy()->setTime(12, 0));
        $this->assertEquals(2.0, (float) $req->duration_hours);
    }

    /** 4. Time off spilling outside working hours rejected. */
    public function test_time_off_outside_working_hours_rejected(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_TIME_OFF]);
        $sun = $this->nextWorkingSunday();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Time off must be entirely within working hours/');

        // 08:00 – 11:00: 08:00 is before the 09:00 block start.
        $this->createRequest('time_off', $st, $sun->copy()->setTime(8, 0), $sun->copy()->setTime(11, 0));
    }

    /** 5. Time off on a full day-off rejected. */
    public function test_time_off_on_day_off_rejected(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_TIME_OFF]);
        $fri = $this->nextDayOff();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Time off cannot be taken on a day off/');

        $this->createRequest('time_off', $st, $fri->copy()->setTime(10, 0), $fri->copy()->setTime(12, 0));
    }

    /** 6. Leave spanning a day-off is accepted; balance deducted for the full range. */
    public function test_leave_spanning_day_off_accepted_and_full_balance_deducted(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_LEAVE, 'cuts_balance' => true]);
        // Pick a 3-day range that includes a Friday.
        $thu = Carbon::today(); while ($thu->dayOfWeek !== 4) $thu->addDay();
        $req = $this->createRequest('leave', $st, $thu->copy()->startOfDay(), $thu->copy()->addDays(2)->endOfDay());
        $this->assertEquals(3, (float) $req->duration_days);

        $balBefore = (float) $this->balanceSvc->getOrCreate($this->employee)->leave_days_balance;
        $this->svc->decide($req, 'manager', 'approve', null, $this->managerUser->id);
        $this->svc->decide($req->refresh(), 'hr', 'approve', null, $this->hrUser->id);
        $balAfter = (float) $this->balanceSvc->getOrCreate($this->employee)->refresh()->leave_days_balance;

        $this->assertSame(EmployeeRequest::STATE_APPROVED, $req->refresh()->state);
        $this->assertEquals($balBefore - 3, $balAfter, 'Balance should drop by 3 days even with a day-off in range');
    }

    /** 7. Full approval flow with side effects. */
    public function test_manager_plus_hr_approval_runs_attendance_side_effects(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_LEAVE, 'cuts_balance' => true]);
        $sun = $this->nextWorkingSunday();
        $req = $this->createRequest('leave', $st, $sun->copy()->startOfDay(), $sun->copy()->endOfDay());

        $this->svc->decide($req, 'manager', 'approve', null, $this->managerUser->id);
        $this->svc->decide($req->refresh(), 'hr', 'approve', null, $this->hrUser->id);

        $req->refresh();
        $this->assertSame(EmployeeRequest::STATE_APPROVED, $req->state);

        $att = Attendance::where('employee_id', $this->employee->id)
            ->whereDate('attendance_date', $sun->toDateString())->first();
        $this->assertNotNull($att);
        $this->assertEquals($req->id, $att->request_id);
        $this->assertTrue((bool) $att->is_day_off || (bool) $att->is_absence === false,
            'Approved leave row should not be marked as an absence');
    }

    /** 8. Manager rejects -> rejected, no side effects. */
    public function test_manager_rejection_locks_request_with_no_side_effects(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_LEAVE, 'cuts_balance' => true]);
        $sun = $this->nextWorkingSunday();
        $req = $this->createRequest('leave', $st, $sun->copy()->startOfDay(), $sun->copy()->endOfDay());

        $balBefore = (float) $this->balanceSvc->getOrCreate($this->employee)->leave_days_balance;
        $this->svc->decide($req, 'manager', 'reject', 'no', $this->managerUser->id);

        $this->assertSame(EmployeeRequest::STATE_REJECTED, $req->refresh()->state);
        $this->assertEquals($balBefore, (float) $this->balanceSvc->getOrCreate($this->employee)->refresh()->leave_days_balance);
        $this->assertSame(0, Attendance::where('request_id', $req->id)->count());
    }

    /** 9. HR override approves alone. */
    public function test_hr_override_approves_without_manager(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_LEAVE, 'cuts_balance' => false]);
        $sun = $this->nextWorkingSunday();
        $req = $this->createRequest('leave', $st, $sun->copy()->startOfDay(), $sun->copy()->endOfDay());

        $this->svc->decide($req, 'hr', 'approve', null, $this->hrUser->id);
        $req->refresh();

        $this->assertSame(EmployeeRequest::STATE_APPROVED, $req->state);
        $this->assertSame(EmployeeRequest::STATE_PENDING,  $req->manager_status, 'Manager status unchanged when HR overrides');
        $this->assertSame(EmployeeRequest::STATE_APPROVED, $req->hr_status);
    }

    /** 10. HR rejection after manager approved. */
    public function test_hr_rejection_after_manager_approval_rejects(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_LEAVE, 'cuts_balance' => false]);
        $sun = $this->nextWorkingSunday();
        $req = $this->createRequest('leave', $st, $sun->copy()->startOfDay(), $sun->copy()->endOfDay());

        $this->svc->decide($req, 'manager', 'approve', null, $this->managerUser->id);
        $this->svc->decide($req->refresh(), 'hr', 'reject', 'budget', $this->hrUser->id);

        $req->refresh();
        $this->assertSame(EmployeeRequest::STATE_REJECTED, $req->state);
        $this->assertEquals('budget', $req->hr_decision_reason);
    }

    /** 11. Insufficient leave balance blocks HR approval. */
    public function test_insufficient_balance_blocks_hr_approval(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_LEAVE, 'cuts_balance' => true]);
        // Drain the balance.
        $this->balanceSvc->getOrCreate($this->employee)->update(['leave_days_balance' => 1]);

        $thu = Carbon::today(); while ($thu->dayOfWeek !== 4) $thu->addDay();
        $req = $this->createRequest('leave', $st, $thu->copy()->startOfDay(), $thu->copy()->addDays(2)->endOfDay()); // 3 days

        $this->svc->decide($req, 'manager', 'approve', null, $this->managerUser->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Insufficient leave-days balance/');
        $this->svc->decide($req->refresh(), 'hr', 'approve', null, $this->hrUser->id);
    }

    /** 12. Approved time off reduces expected_hours and tags attendance.request_id. */
    public function test_approved_time_off_reduces_expected_hours(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_TIME_OFF, 'cuts_balance' => true]);
        $sun = $this->nextWorkingSunday();
        $req = $this->createRequest('time_off', $st, $sun->copy()->setTime(10, 0), $sun->copy()->setTime(13, 0)); // 3h

        $this->svc->decide($req, 'manager', 'approve', null, $this->managerUser->id);
        $this->svc->decide($req->refresh(), 'hr', 'approve', null, $this->hrUser->id);

        $att = Attendance::where('employee_id', $this->employee->id)
            ->whereDate('attendance_date', $sun->toDateString())->first();
        $this->assertNotNull($att);
        $this->assertSame($req->id, $att->request_id);
        // Schedule day = 8h, reduced by 3h.
        $this->assertEquals(5.0, (float) $att->expected_hours);
    }

    /** 13. Approved overtime adds to attendance.approved_overtime_hours. */
    public function test_approved_overtime_logs_approved_overtime_on_attendance(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_OVERTIME, 'cuts_balance' => false, 'factor' => 2.0]);
        $fri = $this->nextDayOff();
        $req = $this->createRequest('overtime', $st, $fri->copy()->setTime(9, 0), $fri->copy()->setTime(12, 0)); // 3h x2 = 6

        $this->svc->decide($req, 'manager', 'approve', null, $this->managerUser->id);
        $this->svc->decide($req->refresh(), 'hr', 'approve', null, $this->hrUser->id);

        $att = Attendance::where('employee_id', $this->employee->id)
            ->whereDate('attendance_date', $fri->toDateString())->first();
        $this->assertNotNull($att);
        $this->assertSame($req->id, $att->request_id);
        $this->assertEquals(6.0, (float) $att->approved_overtime_hours);
    }

    /** 14. Balance cron: accumulative, capped at max. */
    public function test_balance_cron_accumulates_leave_capped_at_max(): void
    {
        $bal = $this->balanceSvc->getOrCreate($this->employee);
        $bal->update(['leave_days_balance' => 28.5, 'last_credited_month' => Carbon::today()->subMonth()->startOfMonth()->toDateString()]);

        // Config: 2/month, max 30.
        $this->balanceSvc->creditMonthly(Carbon::today()->startOfMonth());

        $bal->refresh();
        $this->assertEquals(30.0, (float) $bal->leave_days_balance, 'Capped at max');
    }

    /** 15. Balance cron: time off resets every month. */
    public function test_balance_cron_resets_time_off_each_month(): void
    {
        $bal = $this->balanceSvc->getOrCreate($this->employee);
        $bal->update(['time_off_hours_balance' => 2, 'last_credited_month' => Carbon::today()->subMonth()->startOfMonth()->toDateString()]);

        $this->balanceSvc->creditMonthly(Carbon::today()->startOfMonth());

        $bal->refresh();
        $this->assertEquals(8.0, (float) $bal->time_off_hours_balance, 'Time off resets to per-month value');
    }

    /** 16. Cross-company isolation — request only visible to its company. */
    public function test_cross_company_isolation_in_policy(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'currency' => 'USD']);
        $otherCal = ResourceCalendar::create(['name' => 'X', 'timezone' => 'UTC', 'hours_per_day' => 8, 'company_id' => $otherCompany->id, 'active' => true]);
        $other = Employee::create([
            'name' => 'O', 'employee_code' => 'O1', 'company_id' => $otherCompany->id,
            'resource_calendar_id' => $otherCal->id, 'employment_status' => 'active',
            'hire_date' => '2024-01-01', 'active' => true,
        ]);
        // Make a request directly via the model (skip validation since calendar is empty).
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_LEAVE]);
        $req = EmployeeRequest::create([
            'employee_id' => $other->id, 'company_id' => $otherCompany->id, 'type' => 'leave',
            'subtype_id' => $st->id, 'start_at' => Carbon::tomorrow(), 'end_at' => Carbon::tomorrow()->endOfDay(),
            'duration_days' => 1, 'state' => 'pending',
        ]);
        // Manager from MY company should NOT be able to approve.
        $this->assertFalse($this->managerUser->can('approveAsManager', $req));
    }

    /** 17. Overlapping pending request rejected at submit. */
    public function test_overlap_with_pending_request_rejected_at_submit(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_LEAVE]);
        $sun = $this->nextWorkingSunday();
        $this->createRequest('leave', $st, $sun->copy()->startOfDay(), $sun->copy()->endOfDay());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/already have another request/');
        $this->createRequest('leave', $st, $sun->copy()->startOfDay(), $sun->copy()->endOfDay());
    }

    /** 18. Locked request cannot be re-decided. */
    public function test_locked_request_cannot_be_redecided(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_LEAVE, 'cuts_balance' => false]);
        $sun = $this->nextWorkingSunday();
        $req = $this->createRequest('leave', $st, $sun->copy()->startOfDay(), $sun->copy()->endOfDay());

        $this->svc->decide($req, 'manager', 'reject', 'no', $this->managerUser->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/already been decided/');
        $this->svc->decide($req->refresh(), 'hr', 'approve', null, $this->hrUser->id);
    }

    // ── Audit-found regressions ──────────────────────────────────────────────

    /** 19. HR cannot view a request from a company outside their active scope. */
    public function test_hr_view_blocked_outside_active_companies(): void
    {
        $other = Company::create(['name' => 'Other', 'currency' => 'USD']);
        $otherCal = ResourceCalendar::create(['name' => 'X', 'timezone' => 'UTC', 'hours_per_day' => 8, 'company_id' => $other->id, 'active' => true]);
        $otherEmp = Employee::create([
            'name' => 'O', 'employee_code' => 'O1', 'company_id' => $other->id,
            'resource_calendar_id' => $otherCal->id, 'employment_status' => 'active',
            'hire_date' => '2024-01-01', 'active' => true,
        ]);
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_LEAVE]);
        $otherReq = EmployeeRequest::create([
            'employee_id' => $otherEmp->id, 'company_id' => $other->id, 'type' => 'leave',
            'subtype_id' => $st->id, 'start_at' => Carbon::tomorrow(), 'end_at' => Carbon::tomorrow()->endOfDay(),
            'duration_days' => 1, 'state' => 'pending',
        ]);

        // HR user can only see the seed company. switch() filters the input
        // by allowed companies, so the "other" company won't appear in the
        // active list even if maliciously passed.
        $this->hrUser->companies()->sync([$this->company->id]);
        app(\App\Services\Company\CompanyContextService::class)->switch([$this->company->id]);

        $this->assertFalse($this->hrUser->can('view',        $otherReq));
        $this->assertFalse($this->hrUser->can('approveAsHr', $otherReq));
    }

    /** 20. Self-service employee can view their own attachment even with no requests.read perm. */
    public function test_self_service_employee_can_view_own_attachment(): void
    {
        $selfUser = User::create([
            'name' => 'Self', 'email' => 'self@example.com', 'password' => bcrypt('x'), 'active' => true,
        ]);
        $selfPerm = \App\Models\Security\Permission::where('key', 'attendance.self.request')->first();
        $selfRole = \App\Models\Security\Role::create(['name' => 'Self', 'key' => 'self_test', 'description' => 't', 'active' => true]);
        $selfRole->permissions()->attach($selfPerm->id);
        $selfUser->roles()->attach($selfRole->id);
        $this->employee->update(['user_id' => $selfUser->id]);

        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_LEAVE]);
        $sun = $this->nextWorkingSunday();
        $req = $this->createRequest('leave', $st, $sun->copy()->startOfDay(), $sun->copy()->endOfDay());

        // The policy view() must accept the submitter as a valid viewer — that's
        // exactly the gate the FileController uses on context = EmployeeRequest.
        $this->assertTrue($selfUser->can('view', $req->fresh()));
    }

    /** 21. Concurrency: second decide() after a successful one is rejected by the row lock + isLocked check. */
    public function test_concurrent_double_approval_is_rejected_by_lock(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_LEAVE, 'cuts_balance' => true]);
        $sun = $this->nextWorkingSunday();
        $req = $this->createRequest('leave', $st, $sun->copy()->startOfDay(), $sun->copy()->endOfDay());

        $this->svc->decide($req, 'manager', 'approve', null, $this->managerUser->id);
        $balBefore = (float) $this->balanceSvc->getOrCreate($this->employee)->leave_days_balance;
        $this->svc->decide($req->refresh(), 'hr', 'approve', null, $this->hrUser->id);

        // Second HR call must throw — even though we re-fetched, the request is now locked.
        $thrown = false;
        try {
            $this->svc->decide($req->refresh(), 'hr', 'approve', null, $this->hrUser->id);
        } catch (RuntimeException) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'Second HR approval must be rejected post-lock');

        // Balance was deducted exactly once.
        $balAfter = (float) $this->balanceSvc->getOrCreate($this->employee)->refresh()->leave_days_balance;
        $this->assertEquals($balBefore - 1, $balAfter, 'Balance deducted exactly once, no double-deduct from re-call');
    }

    /** 22. Attendance has no delete route (immutable history). */
    public function test_attendance_has_no_delete_route(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('employees.attendances.delete'),
            'Attendance delete route was removed — this assertion guards against accidental re-introduction.');
    }

    // ── Audit round 2 ────────────────────────────────────────────────────────

    /** 23. Attachment validation rejects SVG (Rule 10 — stored-XSS surface). */
    public function test_attachment_validation_rejects_svg(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_LEAVE]);
        $sun = $this->nextWorkingSunday();

        $request = \App\Http\Requests\Employees\StoreEmployeeRequestRequest::create(
            "/employees/requests", "POST",
            [
                "employee_id" => $this->employee->id,
                "subtype_id"  => $st->id,
                "type"        => "leave",
                "start_at"    => $sun->toDateTimeString(),
                "end_at"      => $sun->copy()->endOfDay()->toDateTimeString(),
            ],
            [], [
                "attachment" => new \Illuminate\Http\Testing\File(
                    "evil.svg",
                    tmpfile(),
                ),
            ],
        );
        $request->setLaravelSession($this->app["session.store"]);
        $request->setUserResolver(fn () => $this->hrUser);

        $v = \Illuminate\Support\Facades\Validator::make(
            $request->all() + ['attachment' => $request->file('attachment')],
            $request->rules(),
        );
        $this->assertTrue($v->fails(), 'SVG attachment must fail validation');
        $this->assertArrayHasKey('attachment', $v->errors()->toArray(), 'Error key must be attachment');
    }

    /** 24. Render the request-create page — confirm no JSON-in-attribute leak. */
    public function test_request_create_page_does_not_leak_json_into_text(): void
    {
        // Grant the perm needed for the create route (separate from hr_approve).
        $writePerm = \App\Models\Security\Permission::where('key', 'attendance.requests.write')->first();
        $this->hrUser->roles->first()->permissions()->attach($writePerm->id);
        // Self-only enforcement requires the user be linked to an employee.
        Employee::create([
            'name' => 'HR For Render', 'employee_code' => 'HR_RENDER',
            'company_id' => $this->company->id, 'resource_calendar_id' => $this->calendar->id,
            'user_id' => $this->hrUser->id, 'employment_status' => 'active',
            'hire_date' => '2024-01-01', 'active' => true,
        ]);

        Auth::login($this->hrUser);
        $response = $this->get('/employees/requests/create');
        $response->assertStatus(200);
        $body = $response->getContent();

        // The JSON island must be present...
        $this->assertStringContainsString('id="request-form-subtypes"', $body);

        // ...and the Alpine JS expression must NOT have leaked into rendered text.
        // If x-data was closed early, the literal substring `duration() {` would
        // appear directly in body text outside an attribute.
        $bodyText = strip_tags($body);
        $this->assertStringNotContainsString('this.subtypes.find(', $bodyText,
            'Alpine expression leaked into the page as text — x-data attribute closed prematurely.');
    }

    /** 26. HR cannot submit a request on behalf of another employee — request body is ignored, employee_id forced to actor's own. */
    public function test_hr_user_cannot_submit_for_another_employee(): void
    {
        // HR + write user with their own employee record.
        $hrEmployee = Employee::create([
            'name' => 'HR Person', 'employee_code' => 'HR1', 'company_id' => $this->company->id,
            'resource_calendar_id' => $this->calendar->id, 'user_id' => $this->hrUser->id,
            'employment_status' => 'active', 'hire_date' => '2024-01-01', 'active' => true,
        ]);
        // Seed a balance for the HR employee so the form-layer balance check
        // doesn't block the submission for a reason unrelated to this test.
        $this->balanceSvc->getOrCreate($hrEmployee)
            ->update(['leave_days_balance' => 20, 'time_off_hours_balance' => 16]);

        $writePerm = \App\Models\Security\Permission::where('key', 'attendance.requests.write')->first();
        $this->hrUser->roles->first()->permissions()->attach($writePerm->id);

        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_LEAVE]);
        $sun = $this->nextWorkingSunday();

        // HR posts a request with someone else's employee_id.
        Auth::login($this->hrUser);
        $response = $this->post('/employees/requests', [
            'employee_id' => $this->employee->id,   // attempted: someone else
            'subtype_id'  => $st->id,
            'type'        => 'leave',
            'start_at'    => $sun->copy()->startOfDay()->toDateTimeString(),
            'end_at'      => $sun->copy()->endOfDay()->toDateTimeString(),
        ]);
        $response->assertRedirect();

        // Whatever was created must belong to HR's OWN employee, not the
        // posted one. If no request exists, that also passes the spec — but
        // the redirect implies one was created.
        $req = EmployeeRequest::latest('id')->first();
        $this->assertNotNull($req, 'A request should have been created');
        $this->assertSame(
            $hrEmployee->id, $req->employee_id,
            'Posted employee_id was honored — backend did not force self-only as required.',
        );
        $this->assertNotSame($this->employee->id, $req->employee_id);
    }

    /** 30. Duration cap: leave > 365 days is rejected. */
    public function test_leave_duration_cap_rejects_over_365_days(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_LEAVE]);
        $sun = $this->nextWorkingSunday();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/365 days/');
        $this->createRequest('leave', $st, $sun->copy()->startOfDay(), $sun->copy()->addDays(400)->endOfDay());
    }

    /** 31. Duration cap: time-off > 48 hours is rejected. */
    public function test_time_off_duration_cap_rejects_over_48_hours(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_TIME_OFF]);
        $sun = $this->nextWorkingSunday();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/48 hours/');
        $this->createRequest('time_off', $st, $sun->copy()->setTime(10, 0), $sun->copy()->addDays(3)->setTime(11, 0));
    }

    /** 32. Subtype name uniqueness within the same company. */
    public function test_subtype_name_unique_within_company(): void
    {
        // Standard-SQL NULL collisions are allowed by the unique constraint;
        // the constraint only enforces uniqueness for rows that share a
        // non-null company_id. Test with an explicit company.
        $this->makeSubtype(['name' => 'Dup', 'type' => RequestSubtype::TYPE_LEAVE, 'company_id' => $this->company->id]);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->makeSubtype(['name' => 'Dup', 'type' => RequestSubtype::TYPE_LEAVE, 'company_id' => $this->company->id]);
    }

    /** 34. Approval of a request whose subtype was soft-deleted still works. */
    public function test_approval_works_after_subtype_soft_deleted(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_OVERTIME, 'factor' => 2.0]);
        $fri = $this->nextDayOff();
        $req = $this->createRequest('overtime', $st, $fri->copy()->setTime(9, 0), $fri->copy()->setTime(12, 0));

        // Admin archives the subtype after submission.
        $st->delete(); // soft delete
        $this->assertTrue($st->fresh()->trashed());

        // Approval must still resolve the subtype + apply the factor — no
        // null->factor crash on the historical row.
        $this->svc->decide($req, 'manager', 'approve', null, $this->managerUser->id);
        $this->svc->decide($req->refresh(), 'hr', 'approve', null, $this->hrUser->id);
        $req->refresh();
        $this->assertSame(EmployeeRequest::STATE_APPROVED, $req->state);

        $att = Attendance::where('employee_id', $this->employee->id)
            ->whereDate('attendance_date', $fri->toDateString())->first();
        $this->assertEquals(6.0, (float) $att->approved_overtime_hours, '3h × factor 2.0 should still apply');
    }

    /** 33. Balance cron: future last_credited_month doesn't over-credit. */
    public function test_balance_cron_handles_future_last_credited_month(): void
    {
        $bal = $this->balanceSvc->getOrCreate($this->employee);
        // Simulate clock skew / restored backup with a future credit timestamp.
        $bal->update(['leave_days_balance' => 5, 'last_credited_month' => Carbon::today()->addMonths(3)->startOfMonth()->toDateString()]);

        $this->balanceSvc->creditMonthly(Carbon::today()->startOfMonth());
        $bal->refresh();
        // Signed diff is negative, max(0, ...) clamps to 0 → no credit applied.
        $this->assertEquals(5.0, (float) $bal->leave_days_balance, 'No over-credit on future last_credited_month');
    }

    /** 29. Submission notifies the assigned attendance manager. */
    public function test_submission_notifies_attendance_manager(): void
    {
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_LEAVE]);
        $sun = $this->nextWorkingSunday();
        \App\Models\Notification::query()->delete();

        $this->createRequest('leave', $st, $sun->copy()->startOfDay(), $sun->copy()->endOfDay());

        $count = \App\Models\Notification::where('user_id', $this->managerUser->id)
            ->where('title', 'New request awaiting your approval')->count();
        $this->assertSame(1, $count, 'Manager should receive a notification on submission');
    }

    /** 27. User with no Employee record cannot submit at all. */
    public function test_user_without_employee_record_cannot_submit(): void
    {
        // hrUser has no employee record; only attendance.hr_approve perm.
        Auth::login($this->hrUser);
        $st = $this->makeSubtype(['type' => RequestSubtype::TYPE_LEAVE]);
        $sun = $this->nextWorkingSunday();

        // Need write perm to even reach the controller method.
        $writePerm = \App\Models\Security\Permission::where('key', 'attendance.requests.write')->first();
        $this->hrUser->roles->first()->permissions()->attach($writePerm->id);

        $response = $this->post('/employees/requests', [
            'subtype_id' => $st->id, 'type' => 'leave',
            'start_at' => $sun->copy()->startOfDay()->toDateTimeString(),
            'end_at'   => $sun->copy()->endOfDay()->toDateTimeString(),
        ]);
        // Friendly redirect (not raw 422) so the user gets a clean page; the
        // controller also calls Log::warning so HR can debug from laravel.log.
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(0, EmployeeRequest::count(),
            'No request should have been created');
    }

    /** 25. Dashboard widget labels read "Balance", not "per Month". */
    public function test_dashboard_widget_balance_labels_are_correct(): void
    {
        // Self-service user with an employee record.
        $selfUser = User::create([
            'name' => 'Self2', 'email' => 'self2@example.com', 'password' => bcrypt('x'), 'active' => true,
        ]);
        $selfPerm = \App\Models\Security\Permission::where('key', 'attendance.self.request')->first();
        $selfRole = \App\Models\Security\Role::create(['name' => 'S2', 'key' => 's2_test', 'description' => 't', 'active' => true]);
        $selfRole->permissions()->attach($selfPerm->id);
        $selfUser->roles()->attach($selfRole->id);
        $this->employee->update(['user_id' => $selfUser->id]);

        Auth::login($selfUser);
        $response = $this->get('/dashboard');
        $response->assertStatus(200);
        $body = $response->getContent();

        $this->assertStringContainsString('Leave Days Balance',     $body);
        $this->assertStringContainsString('Time-off Hours Balance', $body);
        // The two old wrong labels must NOT appear in the widget.
        $this->assertStringNotContainsString('Leave Days per Month',     $body);
        $this->assertStringNotContainsString('Time-off Hours per Month', $body);
    }
}
