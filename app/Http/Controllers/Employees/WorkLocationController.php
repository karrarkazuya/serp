<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\WorkLocation;
use App\Services\Company\CompanyContextService;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkLocationController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', WorkLocation::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = WorkLocation::query()->with('company');

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

        $locations = $query->withCount('employees')->paginate(50)->withQueryString();

        return view('employees.work-locations.index', compact('locations'));
    }

    public function show(WorkLocation $location)
    {
        $this->authorize('view', $location);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($location->company_id, $activeCompanyIds), 403);

        $location->load(['company', 'employees.job']);

        return view('employees.work-locations.show', compact('location'));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', WorkLocation::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;

        return view('employees.work-locations.create', compact('defaultCompanyId'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', WorkLocation::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $companyRule      = Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds);

        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'address'    => 'nullable|string',
            'latitude'   => 'nullable|numeric',
            'longitude'  => 'nullable|numeric',
            'active'     => 'boolean',
            'company_id' => ['nullable', $companyRule],
        ]);

        $location = DB::transaction(fn () => WorkLocation::create($data));

        return redirect()->route('employees.work-locations.show', $location)->with('success', 'Work location created.');
    }

    public function edit(WorkLocation $location)
    {
        $this->authorize('update', $location);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($location->company_id, $activeCompanyIds), 403);

        return view('employees.work-locations.edit', compact('location'));
    }

    public function write(Request $request, WorkLocation $location)
    {
        $this->authorize('update', $location);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($location->company_id, $activeCompanyIds), 403);

        $companyRule = Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds);

        $data = $request->validate([
            'name'       => 'sometimes|required|string|max:255',
            'address'    => 'nullable|string',
            'latitude'   => 'nullable|numeric',
            'longitude'  => 'nullable|numeric',
            'active'     => 'boolean',
            'company_id' => ['nullable', $companyRule],
        ]);

        DB::transaction(fn () => $location->update($data));

        return redirect()->route('employees.work-locations.show', $location)->with('success', 'Work location updated.');
    }

    public function archive(Request $_request, WorkLocation $location)
    {
        $this->authorize('update', $location);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($location->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $location->update(['active' => false]));

        return redirect()->route('employees.work-locations.index')->with('success', 'Work location archived.');
    }

    public function unarchive(Request $_request, WorkLocation $location)
    {
        $this->authorize('update', $location);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($location->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $location->update(['active' => true]));

        return redirect()->route('employees.work-locations.show', $location)->with('success', 'Work location restored.');
    }

    public function unlink(Request $_request, WorkLocation $location)
    {
        $this->authorize('delete', $location);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($location->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $location->delete());

        return redirect()->route('employees.work-locations.index')->with('success', 'Work location deleted.');
    }

    public function addComment(Request $request, WorkLocation $location)
    {
        $this->authorize('comment', $location);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($location->company_id, $activeCompanyIds), 403);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $location->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }
}
