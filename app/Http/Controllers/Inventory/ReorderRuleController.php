<?php

namespace App\Http\Controllers\Inventory;

use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreReorderRuleRequest;
use App\Models\Inventory\Location;
use App\Models\Inventory\OperationType;
use App\Models\Inventory\Picking;
use App\Models\Inventory\Quant;
use App\Models\Inventory\ReorderRule;
use App\Services\Company\CompanyContextService;
use App\Services\Inventory\PickingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReorderRuleController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly PickingService $pickingService,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', ReorderRule::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        // Refresh all on-hand quantities in one pass: one aggregate query + one transaction
        $onHandMap = Quant::selectRaw('company_id, product_id, location_id, SUM(quantity) as total')
            ->whereIn('company_id', $activeCompanyIds)
            ->groupBy('company_id', 'product_id', 'location_id')
            ->get()
            ->keyBy(fn ($q) => "{$q->company_id}_{$q->product_id}_{$q->location_id}");

        DB::transaction(function () use ($activeCompanyIds, $onHandMap) {
            ReorderRule::whereIn('company_id', $activeCompanyIds)->where('active', true)
                ->each(function (ReorderRule $rule) use ($onHandMap) {
                    $key    = "{$rule->company_id}_{$rule->product_id}_{$rule->location_id}";
                    $onHand = (float) ($onHandMap[$key]->total ?? 0);
                    if (abs((float) $rule->qty_on_hand - $onHand) > 0.0001) {
                        $rule->update(['qty_on_hand' => $onHand]);
                    }
                });
        });

        $query = ReorderRule::query()->with(['product.uom', 'location', 'warehouse', 'route'])->forCompanies($activeCompanyIds);

        if ($request->query('filter') === 'needs_replenishment') {
            $query->whereColumn('qty_on_hand', '<=', 'qty_min');
        }

        SearchFilters::apply($query, $request);
        SortsTable::apply($query, $request);
        $rules = $query->paginate(24)->withQueryString();

        return view('inventory.replenishment.index', compact('rules'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', ReorderRule::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;
        return view('inventory.replenishment.create', compact('defaultCompanyId'));
    }

    public function store(StoreReorderRuleRequest $request)
    {
        $data = $request->validated();
        $data['qty_multiple'] = $data['qty_multiple'] ?? 1;
        $data['qty_on_hand']  = 0;
        $data['qty_forecast'] = 0;
        $data['active']       = true;
        $data['created_by']   = auth()->id();
        $data['updated_by']   = auth()->id();

        DB::transaction(fn () => ReorderRule::create($data));
        return redirect()->route('inventory.replenishment.index')->with('success', 'Reorder rule created.');
    }

    public function edit(Request $request, ReorderRule $reorderRule)
    {
        $this->authorize('update', $reorderRule);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($reorderRule->company_id, $activeCompanyIds), 403);
        return view('inventory.replenishment.edit', compact('reorderRule'));
    }

    public function write(Request $request, ReorderRule $reorderRule)
    {
        $this->authorize('update', $reorderRule);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($reorderRule->company_id, $activeCompanyIds), 403);

        $data = $request->validate([
            'qty_min'      => ['required', 'numeric', 'min:0'],
            'qty_max'      => ['required', 'numeric', 'gte:qty_min'],
            'qty_multiple' => ['nullable', 'numeric', 'min:1'],
            'lead_days'    => ['nullable', 'integer', 'min:0'],
            'route_id'     => ['nullable', 'exists:inventory_routes,id'],
        ]);

        DB::transaction(fn () => $reorderRule->update(array_merge($data, ['updated_by' => auth()->id()])));
        return redirect()->route('inventory.replenishment.index')->with('success', 'Reorder rule updated.');
    }

    public function replenish(Request $request, ReorderRule $reorderRule)
    {
        $this->authorize('create', Picking::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($reorderRule->company_id, $activeCompanyIds), 403);

        $reorderRule->load(['product', 'location', 'warehouse']);

        $opType = OperationType::where('company_id', $reorderRule->company_id)
            ->where('code', 'incoming')
            ->when($reorderRule->warehouse_id, fn($q) => $q->where('warehouse_id', $reorderRule->warehouse_id))
            ->first();

        if (!$opType) {
            return back()->with('error', 'No receipt operation type found for this warehouse.');
        }

        $supplierLoc = Location::where('usage', 'supplier')->whereNull('company_id')->first();
        $qty = $reorderRule->getReplenishQty();
        if ($qty <= 0) {
            return back()->with('error', 'Stock is already at or above the maximum quantity. No replenishment needed.');
        }

        $pickingData = [
            'company_id'        => $reorderRule->company_id,
            'operation_type_id' => $opType->id,
            'location_src_id'   => $supplierLoc?->id ?? $opType->default_location_src_id,
            'location_dest_id'  => $reorderRule->location_id,
            'scheduled_date'    => now()->addDays($reorderRule->lead_days),
            'origin'            => 'Replenishment',
            'active'            => true,
            'created_by'        => auth()->id(),
            'updated_by'        => auth()->id(),
        ];

        $movesData = [[
            'product_id'  => $reorderRule->product_id,
            'uom_id'      => $reorderRule->product->uom_id,
            'product_qty' => $qty,
            'state'       => 'draft',
            'name'        => $reorderRule->product->name,
            'date'        => now()->addDays($reorderRule->lead_days)->toDateString(),
            'created_by'  => auth()->id(),
            'updated_by'  => auth()->id(),
        ]];

        $picking = DB::transaction(fn () => $this->pickingService->create($pickingData, $movesData));

        return redirect()->route('inventory.transfers.show', $picking)->with('success', 'Replenishment order created.');
    }

    public function unlink(Request $_request, ReorderRule $reorderRule)
    {
        $this->authorize('delete', $reorderRule);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($reorderRule->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $reorderRule->delete());
        return redirect()->route('inventory.replenishment.index')->with('success', 'Reorder rule deleted.');
    }
}
