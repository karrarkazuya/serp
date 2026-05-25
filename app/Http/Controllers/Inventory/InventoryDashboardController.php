<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Lot;
use App\Models\Inventory\OperationType;
use App\Models\Inventory\Picking;
use App\Models\Inventory\Product;
use App\Models\Inventory\Quant;
use App\Models\Inventory\ReorderRule;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;
// Picking is still used by withCount() callbacks referencing STATE_ constants.

class InventoryDashboardController extends Controller
{
    public function __construct(private readonly CompanyContextService $companyContext) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('inventory.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        // Counts per operation type
        $operationTypes = OperationType::whereIn('company_id', $activeCompanyIds)
            ->where('active', true)
            ->withCount([
                'pickings as ready_count' => fn($q) => $q->where('state', Picking::STATE_ASSIGNED),
                'pickings as todo_count'  => fn($q) => $q->whereIn('state', [Picking::STATE_CONFIRMED, Picking::STATE_DRAFT]),
            ])
            ->orderBy('code')
            ->get();

        // Alerts: products needing replenishment. R4 perf finding — the old
        // implementation loaded EVERY active rule into memory and filtered
        // in PHP. Predicate is just `qty_on_hand <= qty_min`, which the
        // DB can evaluate via whereColumn.
        $replenishCount = ReorderRule::whereIn('company_id', $activeCompanyIds)
            ->where('active', true)
            ->whereColumn('qty_on_hand', '<=', 'qty_min')
            ->count();

        $productCount = Product::whereIn('company_id', $activeCompanyIds)->where('active', true)->count();
        $lotCount     = Lot::whereIn('company_id', $activeCompanyIds)->count();
        $stockLines   = Quant::whereIn('company_id', $activeCompanyIds)
            ->whereHas('location', fn($q) => $q->where('usage', 'internal'))
            ->where('quantity', '>', 0)
            ->count();

        // R5: removed $readyReceiptsCount / $readyDeliveriesCount / $lowStockCount —
        // the view never displayed them. The operation-type cards above
        // already surface ready/todo counts via withCount, which is what
        // the dashboard actually shows.
        return view('inventory.dashboard', compact(
            'operationTypes',
            'replenishCount',
            'productCount',
            'lotCount',
            'stockLines',
        ));
    }
}
