<?php

namespace App\Http\Controllers\Employees;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreRequestSubtypeRequest;
use App\Http\Requests\Employees\UpdateRequestSubtypeRequest;
use App\Models\Employees\RequestSubtype;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestSubtypeController extends Controller
{
    public function __construct(private readonly CompanyContextService $companyContext) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', RequestSubtype::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = RequestSubtype::query()->with('company');
        // Fail-closed multi-tenant gate (see EmployeeController::read). Note:
        // subtypes with company_id = null are global and stay visible via
        // forCompanies() — empty active list means the actor is in no
        // companies, so they see nothing (including the globals).
        empty($activeCompanyIds)
            ? $query->whereRaw('1 = 0')
            : $query->forCompanies($activeCompanyIds);

        SearchFilters::apply($query, $request);
        if ($request->query('filter') === 'archived')      $query->where('active', false);
        elseif ($request->query('filter') === 'all')       { /* no filter */ }
        else                                                $query->active();

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(RequestSubtype::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with('company')->orderBy('name')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('employees.request-subtypes.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);
        $subtypes = $query->paginate(50)->withQueryString();
        return view('employees.request-subtypes.index', compact('subtypes'));
    }

    public function show(RequestSubtype $subtype)
    {
        $this->authorize('view', $subtype);
        $this->assertWithinActiveCompanies($subtype);
        $subtype->load('company');
        return view('employees.request-subtypes.show', compact('subtype'));
    }

    public function create()
    {
        $this->authorize('create', RequestSubtype::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;
        return view('employees.request-subtypes.create', compact('defaultCompanyId'));
    }

    public function store(StoreRequestSubtypeRequest $request)
    {
        $data = $this->normalize($request->validated());
        $subtype = DB::transaction(fn () => RequestSubtype::create($data));
        return redirect()->route('employees.request-subtypes.show', $subtype)
            ->with('success', __('employees.subtype_created'));
    }

    public function edit(RequestSubtype $subtype)
    {
        $this->authorize('update', $subtype);
        $this->assertWithinActiveCompanies($subtype);
        return view('employees.request-subtypes.edit', compact('subtype'));
    }

    public function write(UpdateRequestSubtypeRequest $request, RequestSubtype $subtype)
    {
        $this->authorize('update', $subtype);
        $this->assertWithinActiveCompanies($subtype);
        $data = $this->normalize($request->validated());
        DB::transaction(fn () => $subtype->update($data));
        return redirect()->route('employees.request-subtypes.show', $subtype)
            ->with('success', __('employees.subtype_updated'));
    }

    public function archive(Request $_request, RequestSubtype $subtype)
    {
        $this->authorize('update', $subtype);
        $this->assertWithinActiveCompanies($subtype);
        DB::transaction(fn () => $subtype->update(['active' => false]));
        return redirect()->route('employees.request-subtypes.index')->with('success', __('employees.subtype_archived'));
    }

    public function unarchive(Request $_request, RequestSubtype $subtype)
    {
        $this->authorize('update', $subtype);
        $this->assertWithinActiveCompanies($subtype);
        DB::transaction(fn () => $subtype->update(['active' => true]));
        return redirect()->route('employees.request-subtypes.show', $subtype)->with('success', __('employees.subtype_unarchived'));
    }

    public function bulkUnlink(Request $request): RedirectResponse
    {
        $this->authorize('delete', RequestSubtype::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $selectAll = $request->boolean('select_all');
        $ids = $request->input('ids', []);

        DB::transaction(function () use ($selectAll, $ids, $activeCompanyIds) {
            $query = RequestSubtype::where(function ($q) use ($activeCompanyIds) {
                $q->whereNull('company_id');
                if (!empty($activeCompanyIds)) {
                    $q->orWhereIn('company_id', $activeCompanyIds);
                }
            });
            if (!$selectAll) {
                $query->whereIn('id', $ids);
            }
            foreach ($query->get() as $subtype) {
                $subtype->delete();
            }
        });

        return redirect()->route('employees.request-subtypes.index')->with('success', __('employees.subtype_deleted'));
    }

    public function unlink(Request $_request, RequestSubtype $subtype)
    {
        $this->authorize('delete', $subtype);
        $this->assertWithinActiveCompanies($subtype);
        DB::transaction(fn () => $subtype->delete());
        return redirect()->route('employees.request-subtypes.index')->with('success', __('employees.subtype_deleted'));
    }

    public function addComment(Request $request, RequestSubtype $subtype)
    {
        $this->authorize('update', $subtype);
        $this->assertWithinActiveCompanies($subtype);
        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $subtype->logComment($request->body));
        return back()->with('success', __('employees.comment_added'));
    }

    /**
     * Enforce that factor=1.0 for any subtype not of type overtime.
     */
    private function normalize(array $data): array
    {
        if (($data['type'] ?? null) !== RequestSubtype::TYPE_OVERTIME) {
            $data['factor'] = 1.0;
        }
        return $data;
    }

    private function assertWithinActiveCompanies(RequestSubtype $subtype): void
    {
        if ($subtype->company_id === null) return; // global
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        // Subtypes with company_id = null are global config; otherwise must be
        // in the actor's active companies. Fail-closed if active list is empty.
        abort_unless(
            $subtype->company_id === null
                ? !empty($activeCompanyIds)
                : (!empty($activeCompanyIds) && in_array($subtype->company_id, $activeCompanyIds, true)),
            403
        );
    }
}
