<?php

namespace App\Http\Controllers\Inventory;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryAdjustment;
use App\Models\Inventory\InventoryAdjustmentLine;
use App\Services\Company\CompanyContextService;
use App\Services\Inventory\AdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryAdjustmentController extends Controller
{
    public function __construct(
        private readonly AdjustmentService $adjustmentService,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', InventoryAdjustment::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = InventoryAdjustment::query()->with('company')->forCompanies($activeCompanyIds);

        if ($request->query('filter') === 'archived') {
            $query->inactive();
        } else {
            $query->active();
        }

        SearchFilters::apply($query, $request);

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(InventoryAdjustment::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with('company')->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('inventory.adjustments.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);

        $adjustments = $query->paginate(24)->withQueryString();

        return view('inventory.adjustments.index', compact('adjustments'));
    }

    public function show(InventoryAdjustment $inventoryAdjustment)
    {
        $this->authorize('view', $inventoryAdjustment);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($inventoryAdjustment->company_id, $activeCompanyIds), 403);

        $inventoryAdjustment->load([
            'lines.product.uom', 'lines.location', 'lines.lot', 'creator', 'updater',
        ]);

        $allIds = InventoryAdjustment::active()->forCompanies($activeCompanyIds)->orderBy('name')->pluck('id');
        $currentIndex   = $allIds->search($inventoryAdjustment->id);
        $prevId         = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId         = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal    = $allIds->count();

        return view('inventory.adjustments.show', compact(
            'inventoryAdjustment', 'prevId', 'nextId', 'recordPosition', 'recordTotal'
        ));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', InventoryAdjustment::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;
        return view('inventory.adjustments.create', compact('defaultCompanyId'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', InventoryAdjustment::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'date'       => ['nullable', 'date'],
            'note'       => ['nullable', 'string'],
            'exhausted'  => ['boolean'],
        ]);

        abort_unless(in_array($data['company_id'], $activeCompanyIds), 403);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $adjustment = DB::transaction(fn () => $this->adjustmentService->create($data));

        return redirect()->route('inventory.adjustments.show', $adjustment)->with('success', 'Physical inventory created.');
    }

    public function startCount(Request $_request, InventoryAdjustment $inventoryAdjustment)
    {
        $this->authorize('update', $inventoryAdjustment);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($inventoryAdjustment->company_id, $activeCompanyIds), 403);
        abort_unless($inventoryAdjustment->isDraft(), 403);
        DB::transaction(fn () => $this->adjustmentService->startCount($inventoryAdjustment));
        return back()->with('success', 'Inventory count started. Update quantities below.');
    }

    public function updateLine(Request $request, InventoryAdjustment $inventoryAdjustment, InventoryAdjustmentLine $line)
    {
        $this->authorize('update', $inventoryAdjustment);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($inventoryAdjustment->company_id, $activeCompanyIds), 403);
        abort_unless($inventoryAdjustment->isInProgress(), 403);
        abort_unless($line->adjustment_id === $inventoryAdjustment->id, 403);

        $data = $request->validate(['inventory_qty' => ['required', 'numeric', 'min:0']]);
        DB::transaction(fn () => $this->adjustmentService->updateLine($line, (float) $data['inventory_qty']));
        return back()->with('success', 'Line updated.');
    }

    public function validateAdjustment(Request $_request, InventoryAdjustment $inventoryAdjustment)
    {
        $this->authorize('update', $inventoryAdjustment);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($inventoryAdjustment->company_id, $activeCompanyIds), 403);

        try {
            DB::transaction(fn () => $this->adjustmentService->validate($inventoryAdjustment));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('inventory.adjustments.show', $inventoryAdjustment)->with('success', 'Physical inventory validated.');
    }

    public function unlink(Request $_request, InventoryAdjustment $inventoryAdjustment)
    {
        $this->authorize('delete', $inventoryAdjustment);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($inventoryAdjustment->company_id, $activeCompanyIds), 403);
        try {
            DB::transaction(fn () => $this->adjustmentService->delete($inventoryAdjustment));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()->route('inventory.adjustments.index')->with('success', 'Physical inventory deleted.');
    }

    public function addComment(Request $request, InventoryAdjustment $inventoryAdjustment)
    {
        $this->authorize('comment', $inventoryAdjustment);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($inventoryAdjustment->company_id, $activeCompanyIds), 403);
        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $inventoryAdjustment->logComment($request->body));
        return back()->with('success', 'Comment added.');
    }
}
