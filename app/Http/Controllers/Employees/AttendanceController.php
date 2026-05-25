<?php

namespace App\Http\Controllers\Employees;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreAttendanceRequest;
use App\Http\Requests\Employees\UpdateAttendanceRequest;
use App\Models\Employees\Attendance;
use App\Services\Company\CompanyContextService;
use App\Services\Employees\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly AttendanceService $attendanceService,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Attendance::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        $query = Attendance::query()->with(['employee:id,name,avatar', 'company:id,name', 'resourceCalendar:id,name']);

        if (!empty($activeCompanyIds)) {
            $query->forCompanies($activeCompanyIds);
        }

        SearchFilters::apply($query, $request);

        switch ($request->query('filter')) {
            case 'absences':
                $query->where('is_absence', true);
                break;
            case 'overtime':
                $query->where('overtime_hours', '>', 0);
                break;
            case 'shortage':
                $query->where('shortage_hours', '>', 0);
                break;
            case 'day_off':
                $query->where('is_day_off', true);
                break;
        }

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(Attendance::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->orderByDesc('attendance_date')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('employees.attendances.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request, 'date', 'desc');

        $attendances = $query->paginate(50)->withQueryString();

        return view('employees.attendances.index', compact('attendances'));
    }

    public function show(Attendance $attendance)
    {
        $this->authorize('view', $attendance);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($attendance->company_id, $activeCompanyIds), 403);

        $attendance->load(['employee.department', 'employee.job', 'company', 'resourceCalendar.attendances']);

        return view('employees.attendances.show', compact('attendance'));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', Attendance::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;

        return view('employees.attendances.create', compact('defaultCompanyId'));
    }

    public function store(StoreAttendanceRequest $request)
    {
        $data = $request->validated();

        // If no resource_calendar was passed, AttendanceService will snapshot
        // the employee's current one.
        $attendance = DB::transaction(fn () => $this->attendanceService->create($data));

        return redirect()->route('employees.attendances.show', $attendance)
            ->with('success', __('employees.attendance_created'));
    }

    public function edit(Attendance $attendance)
    {
        $this->authorize('update', $attendance);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($attendance->company_id, $activeCompanyIds), 403);

        $attendance->load(['employee', 'company', 'resourceCalendar']);

        return view('employees.attendances.edit', compact('attendance'));
    }

    public function write(UpdateAttendanceRequest $request, Attendance $attendance)
    {
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($attendance->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->attendanceService->update($attendance, $request->validated()));

        return redirect()->route('employees.attendances.show', $attendance)
            ->with('success', __('employees.attendance_updated'));
    }

    public function unlink(Request $_request, Attendance $attendance)
    {
        $this->authorize('delete', $attendance);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($attendance->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $attendance->delete());

        return redirect()->route('employees.attendances.index')
            ->with('success', __('employees.attendance_deleted'));
    }

    public function addComment(Request $request, Attendance $attendance)
    {
        $this->authorize('comment', $attendance);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($attendance->company_id, $activeCompanyIds), 403);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $attendance->logComment($request->body));

        return back()->with('success', __('employees.comment_added'));
    }
}
