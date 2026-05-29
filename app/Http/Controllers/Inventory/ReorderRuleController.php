<?php

namespace App\Http\Controllers\Inventory;

use App\Helpers\GroupsQuery;
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

        // R4 finding: the old behaviour ran a full quant-aggregation + every
        // active rule update on EVERY index load — heavy writes on a read
        // endpoint that scaled with rule count. Now opt-in via `?refresh=1`
        // (the "Refresh forecasts" button) and otherwise show the cached
        // qty_on_hand. NOTE: an earlier version of this comment claimed the
        // picking/scrap/adjustment validate paths also updated qty_on_hand —
        // they don't. The cached number only changes on this manual refresh.
        // qty_forecast is set to 0 at create time and is never updated by
        // any code path; expect it to read 0 across the board until the
        // forecasting pipeline is wired up.
        if ($request->boolean('refresh')) {
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
        }

        $query = ReorderRule::query()->with(['product.uom', 'location', 'warehouse', 'route'])->forCompanies($activeCompanyIds);

        if ($request->query('filter') === 'needs_replenishment') {
            $query->whereColumn('qty_on_hand', '<=', 'qty_min');
        }

        SearchFilters::apply($query, $request);

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(ReorderRule::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with(['product.uom', 'location', 'warehouse', 'route'])->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('inventory.replenishment.index', compact('groups'));
            }
        }

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
        return redirect()->route('inventory.replenishment.index')->with('success', __('inventory.created'));
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

        // `route_id` is no longer accepted on edit — see StoreReorderRuleRequest
        // rationale. Column stays in the DB but the form no longer surfaces it,
        // so we don't validate it here either.
        $data = $request->validate([
            'qty_min'      => ['required', 'numeric', 'min:0'],
            'qty_max'      => ['required', 'numeric', 'gte:qty_min'],
            'qty_multiple' => ['nullable', 'numeric', 'min:1'],
            'lead_days'    => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(fn () => $reorderRule->update(array_merge($data, ['updated_by' => auth()->id()])));
        return redirect()->route('inventory.replenishment.index')->with('success', __('inventory.updated'));
    }

    public function replenish(Request $request, ReorderRule $reorderRule)
    {
        $this->authorize('create', Picking::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($reorderRule->company_id, $activeCompanyIds), 403);

        $reorderRule->load(['product.suppliers.partner', 'location', 'warehouse']);

        $opType = OperationType::where('company_id', $reorderRule->company_id)
            ->where('code', 'incoming')
            ->when($reorderRule->warehouse_id, fn($q) => $q->where('warehouse_id', $reorderRule->warehouse_id))
            ->first();

        if (!$opType) {
            return back()->with('error', __('inventory.no_receipt_optype'));
        }

        $supplierLoc = Location::where('usage', 'supplier')->whereNull('company_id')->first();
        $qty = $reorderRule->getReplenishQty();
        if ($qty <= 0) {
            return back()->with('error', __('inventory.already_at_max'));
        }

        // Odoo parity: pick the vendor from the product's ProductSupplier list
        // and use its `delay` as the lead time if the rule itself has none.
        // Without this, the replenishment picking would always land with a
        // missing partner_id (orphan receipts → no one to chase, no PO link
        // upstream) and the wrong scheduled_date (assumes 0 days when the
        // rule was left at default).
        $supplier   = $reorderRule->product->primarySupplier($reorderRule->company_id);
        $leadDays   = (int) $reorderRule->lead_days;
        if ($leadDays <= 0 && $supplier && $supplier->delay > 0) {
            $leadDays = (int) $supplier->delay;
        }
        $scheduledDate = now()->addDays($leadDays);

        $pickingData = [
            'company_id'        => $reorderRule->company_id,
            'operation_type_id' => $opType->id,
            'partner_id'        => $supplier?->partner_id,
            'location_src_id'   => $supplierLoc?->id ?? $opType->default_location_src_id,
            'location_dest_id'  => $reorderRule->location_id,
            'scheduled_date'    => $scheduledDate,
            'origin'            => __('inventory.origin_replenishment'),
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
            'date'        => $scheduledDate->toDateString(),
            'created_by'  => auth()->id(),
            'updated_by'  => auth()->id(),
        ]];

        $picking = DB::transaction(fn () => $this->pickingService->create($pickingData, $movesData));

        return redirect()->route('inventory.transfers.show', $picking)->with('success', __('inventory.replenish_created'));
    }

    public function unlink(Request $_request, ReorderRule $reorderRule)
    {
        $this->authorize('delete', $reorderRule);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($reorderRule->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $reorderRule->delete());
        return redirect()->route('inventory.replenishment.index')->with('success', __('inventory.deleted'));
    }
}
