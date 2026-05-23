<?php

namespace App\Http\Controllers\Inventory\Configuration;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreWarehouseRequest;
use App\Http\Requests\Inventory\UpdateWarehouseRequest;
use App\Models\Inventory\Warehouse;
use App\Services\Company\CompanyContextService;
use App\Services\Inventory\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    public function __construct(
        private readonly WarehouseService $warehouseService,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Warehouse::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = Warehouse::query()->with('company')->forCompanies($activeCompanyIds);
        if ($request->query('filter') !== 'all') $query->active();
        SearchFilters::apply($query, $request);

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(Warehouse::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with('company')->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('inventory.configuration.warehouses.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);
        $warehouses = $query->paginate(24)->withQueryString();
        return view('inventory.configuration.warehouses.index', compact('warehouses'));
    }

    public function show(Warehouse $warehouse)
    {
        $this->authorize('view', $warehouse);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($warehouse->company_id, $activeCompanyIds), 403);
        $warehouse->load(['company', 'partner', 'stockLocation', 'operationTypes', 'creator', 'updater']);

        $allIds = Warehouse::active()->forCompanies($activeCompanyIds)->orderBy('name')->pluck('id');
        $currentIndex = $allIds->search($warehouse->id);
        $prevId = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal    = $allIds->count();

        return view('inventory.configuration.warehouses.show', compact(
            'warehouse', 'prevId', 'nextId', 'recordPosition', 'recordTotal'
        ));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Warehouse::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;
        return view('inventory.configuration.warehouses.create', compact('defaultCompanyId'));
    }

    public function store(StoreWarehouseRequest $request)
    {
        $data = $request->validated();
        $data['active']     = true;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $warehouse = DB::transaction(fn () => $this->warehouseService->create($data));
        return redirect()->route('inventory.config.warehouses.show', $warehouse)->with('success', 'Warehouse created with default locations and operation types.');
    }

    public function edit(Warehouse $warehouse)
    {
        $this->authorize('update', $warehouse);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($warehouse->company_id, $activeCompanyIds), 403);
        return view('inventory.configuration.warehouses.edit', compact('warehouse'));
    }

    public function write(UpdateWarehouseRequest $request, Warehouse $warehouse)
    {
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($warehouse->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $this->warehouseService->update($warehouse, $request->validated()));
        return redirect()->route('inventory.config.warehouses.show', $warehouse)->with('success', 'Warehouse updated.');
    }

    public function archive(Request $_request, Warehouse $warehouse)
    {
        $this->authorize('update', $warehouse);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($warehouse->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $this->warehouseService->archive($warehouse));
        return redirect()->route('inventory.config.warehouses.index')->with('success', 'Warehouse archived.');
    }

    public function unarchive(Request $_request, Warehouse $warehouse)
    {
        $this->authorize('update', $warehouse);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($warehouse->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $this->warehouseService->unarchive($warehouse));
        return redirect()->route('inventory.config.warehouses.show', $warehouse)->with('success', 'Warehouse restored.');
    }

    public function unlink(Request $_request, Warehouse $warehouse)
    {
        $this->authorize('delete', $warehouse);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($warehouse->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $this->warehouseService->delete($warehouse));
        return redirect()->route('inventory.config.warehouses.index')->with('success', 'Warehouse deleted.');
    }

    public function addComment(Request $request, Warehouse $warehouse)
    {
        $this->authorize('comment', $warehouse);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($warehouse->company_id, $activeCompanyIds), 403);
        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $warehouse->logComment($request->body));
        return back()->with('success', 'Comment added.');
    }
}
