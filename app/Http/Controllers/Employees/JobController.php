<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreJobRequest;
use App\Http\Requests\Employees\UpdateJobRequest;
use App\Models\Employees\Job;
use App\Services\Company\CompanyContextService;
use App\Services\Employees\JobService;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobController extends Controller
{
    public function __construct(
        private readonly JobService $jobService,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Job::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = Job::query()->with(['company', 'department'])->withCount('employees');

        if (!empty($activeCompanyIds)) {
            $query->forCompanies($activeCompanyIds);
        }

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->where('active', false);
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        SortsTable::apply($query, $request);

        $jobs = $query->paginate(50)->withQueryString();

        return view('employees.jobs.index', compact('jobs'));
    }

    public function show(Job $job)
    {
        $this->authorize('view', $job);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($job->company_id, $activeCompanyIds), 403);

        $job->load(['company', 'department', 'employees.department']);

        return view('employees.jobs.show', compact('job'));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', Job::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;

        return view('employees.jobs.create', compact('defaultCompanyId'));
    }

    public function store(StoreJobRequest $request)
    {
        $job = DB::transaction(fn () => $this->jobService->create($request->validated()));

        return redirect()->route('employees.jobs.show', $job)->with('success', 'Job position created.');
    }

    public function edit(Job $job)
    {
        $this->authorize('update', $job);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($job->company_id, $activeCompanyIds), 403);

        return view('employees.jobs.edit', compact('job'));
    }

    public function write(UpdateJobRequest $request, Job $job)
    {
        $this->authorize('update', $job);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($job->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->jobService->update($job, $request->validated()));

        return redirect()->route('employees.jobs.show', $job)->with('success', 'Job position updated.');
    }

    public function archive(Request $_request, Job $job)
    {
        $this->authorize('update', $job);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($job->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->jobService->archive($job));

        return redirect()->route('employees.jobs.index')->with('success', 'Job position archived.');
    }

    public function unarchive(Request $_request, Job $job)
    {
        $this->authorize('update', $job);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($job->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->jobService->unarchive($job));

        return redirect()->route('employees.jobs.show', $job)->with('success', 'Job position restored.');
    }

    public function unlink(Request $_request, Job $job)
    {
        $this->authorize('delete', $job);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($job->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->jobService->delete($job));

        return redirect()->route('employees.jobs.index')->with('success', 'Job position deleted.');
    }

    public function addComment(Request $request, Job $job)
    {
        $this->authorize('comment', $job);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($job->company_id, $activeCompanyIds), 403);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $job->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }
}
