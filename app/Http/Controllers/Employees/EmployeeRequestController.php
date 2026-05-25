<?php

namespace App\Http\Controllers\Employees;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\ApproveRejectRequestRequest;
use App\Http\Requests\Employees\StoreEmployeeRequestRequest;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeRequest;
use App\Models\Employees\RequestSubtype;
use App\Services\Company\CompanyContextService;
use App\Services\Employees\EmployeeRequestService;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EmployeeRequestController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly EmployeeRequestService $requestService,
        private readonly FileService $fileService,
    ) {}

    /**
     * HR list. Filtered by active companies. Anyone with attendance.requests.read
     * can see all requests in their companies; self-service users land on the
     * personal list (see myIndex below).
     */
    public function read(Request $request)
    {
        abort_unless($request->user()->hasPermission('attendance.requests.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = EmployeeRequest::query()->with(['employee:id,name,avatar,company_id', 'subtype:id,name,type', 'company:id,name']);
        if (!empty($activeCompanyIds)) $query->forCompanies($activeCompanyIds);

        SearchFilters::apply($query, $request);
        switch ($request->query('filter')) {
            case 'pending':  $query->where('state', EmployeeRequest::STATE_PENDING);  break;
            case 'approved': $query->where('state', EmployeeRequest::STATE_APPROVED); break;
            case 'rejected': $query->where('state', EmployeeRequest::STATE_REJECTED); break;
        }

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(EmployeeRequest::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->orderByDesc('created_at')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('employees.requests.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request, 'created_at', 'desc');
        $requests = $query->paginate(50)->withQueryString();
        return view('employees.requests.index', compact('requests'));
    }

    /**
     * Personal queue (self-service): my requests + requests awaiting my approval.
     * Used by the dashboard widget and by direct route /employees/my-requests.
     */
    public function myIndex(Request $request)
    {
        abort_unless($request->user()->hasPermission('attendance.self.request'), 403);

        $user = $request->user();

        $mine = EmployeeRequest::with(['subtype:id,name,type', 'employee:id,name'])
            ->whereHas('employee', fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $pendingMyApproval = EmployeeRequest::with(['subtype:id,name,type', 'employee:id,name,attendance_manager_id'])
            ->where('manager_status', EmployeeRequest::STATE_PENDING)
            ->where('state', EmployeeRequest::STATE_PENDING)
            ->whereHas('employee.attendanceManager', fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('employees.requests.my-index', compact('mine', 'pendingMyApproval'));
    }

    public function show(Request $request, EmployeeRequest $employeeRequest)
    {
        $this->authorize('view', $employeeRequest);
        $employeeRequest->load([
            'employee.user', 'employee.attendanceManager.user', 'employee.department',
            'company', 'subtype', 'managerDecisionUser', 'hrDecisionUser',
            'attendances',
        ]);
        $fromMy = $request->query('from') === 'my'
            || !$request->user()->hasPermission('attendance.requests.read');
        return view('employees.requests.show', compact('employeeRequest', 'fromMy'));
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->hasPermission('attendance.requests.write')
                  || $request->user()->hasPermission('attendance.self.request'), 403);

        // SECURITY: a request is always for the actor themselves — never for
        // another employee, even if the actor is HR. Redirect with a friendly
        // error (and log a warning so HR can debug from the log) if the user
        // isn't linked to an employee record.
        $myEmployee = Employee::where('user_id', $request->user()->id)->first();
        if ($myEmployee === null) {
            return $this->redirectMissingEmployee($request);
        }

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $fromMy = $request->query('from') === 'my'
            || !$request->user()->hasPermission('attendance.requests.write');

        $subtypes = RequestSubtype::active()
            ->where(function ($q) use ($activeCompanyIds) {
                $q->whereNull('company_id');
                if (!empty($activeCompanyIds)) $q->orWhereIn('company_id', $activeCompanyIds);
            })
            ->orderBy('type')->orderBy('name')->get();

        // Working-day map (S-ERP dow 0=Sat .. 6=Fri) for the day-off hint JS.
        // True = working day in the employee's schedule. Empty calendar = every
        // day treated as working so the hint never fires for an unconfigured emp.
        $workingDays = [0, 1, 2, 3, 4, 5, 6];
        if ($myEmployee->resource_calendar_id) {
            $myEmployee->loadMissing('resourceCalendar.attendances');
            $workingDays = $myEmployee->resourceCalendar?->attendances
                ?->pluck('day_of_week')->unique()->values()->all() ?? [];
        }

        return view('employees.requests.create', compact('myEmployee', 'subtypes', 'fromMy', 'workingDays'));
    }

    public function store(StoreEmployeeRequestRequest $request)
    {
        $data = $request->validated();

        // SECURITY: a request is always for the actor themselves. Any
        // employee_id POSTed (even by HR users) is ignored and overwritten
        // with the current user's own employee record.
        $myEmployee = Employee::where('user_id', $request->user()->id)->first();
        if ($myEmployee === null) {
            return $this->redirectMissingEmployee($request);
        }
        $data['employee_id'] = $myEmployee->id;

        // Handle attachment upload via FileService (Rule 10). Permission gate
        // is null on purpose — access is governed entirely by the parent-request
        // context check in FileController so submitters (who only have
        // attendance.self.request) can still view their own attachments.
        // The actual context is set right after the request is created below.
        $uploadedFile = $request->hasFile('attachment') ? $request->file('attachment') : null;

        try {
            $employeeRequest = DB::transaction(function () use ($data, $uploadedFile) {
                $req = $this->requestService->create($data);
                if ($uploadedFile) {
                    $file = $this->fileService->store(
                        file: $uploadedFile,
                        directory: 'requests',
                        permissionKey: null,
                        context: $req,
                        source: $req,
                    );
                    $req->update(['attachment' => $file->uuid]);
                }
                return $req;
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Preserve self-service navigation context across the redirect.
        $params = ['employeeRequest' => $employeeRequest];
        if ($request->query('from') === 'my' || !$request->user()->hasPermission('attendance.requests.read')) {
            $params['from'] = 'my';
        }
        return redirect()->route('employees.requests.show', $params)
            ->with('success', __('employees.request_submitted'));
    }

    public function decide(ApproveRejectRequestRequest $request, EmployeeRequest $employeeRequest)
    {
        // Determine the role of the caller. HR check first — HR override.
        $role = null;
        if ($request->user()->can('approveAsHr', $employeeRequest)) {
            $role = 'hr';
        } elseif ($request->user()->can('approveAsManager', $employeeRequest)) {
            $role = 'manager';
        }
        abort_unless($role !== null, 403);

        $data = $request->validated();

        try {
            DB::transaction(fn () => $this->requestService->decide(
                $employeeRequest,
                $role,
                $data['decision'],
                $data['reason'] ?? null,
                $request->user()->id,
            ));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('employees.request_decision_recorded'));
    }

    public function addComment(Request $request, EmployeeRequest $employeeRequest)
    {
        $this->authorize('comment', $employeeRequest);
        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $employeeRequest->logComment($request->body));
        return back()->with('success', __('employees.comment_added'));
    }

    /**
     * Called when the actor has no Employee record. Friendly redirect with a
     * flash message + Log::warning so the issue surfaces in laravel.log
     * (abort() with 4xx codes never reaches the log by default).
     */
    private function redirectMissingEmployee(Request $request)
    {
        Log::warning('Request submission blocked — actor has no Employee record', [
            'user_id'    => $request->user()->id,
            'user_email' => $request->user()->email,
            'route'      => $request->route()?->getName(),
            'url'        => $request->fullUrl(),
        ]);
        $target = $request->user()->hasPermission('attendance.requests.read')
            ? route('employees.requests.index')
            : route('dashboard');
        return redirect($target)->with('error', __('employees.request_self_only_no_employee'));
    }
}
