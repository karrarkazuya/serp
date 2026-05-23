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

        // Alerts: products needing replenishment
        $replenishCount = ReorderRule::whereIn('company_id', $activeCompanyIds)
            ->where('active', true)
            ->get()
            ->filter(fn($r) => $r->needsReplenishment())
            ->count();

        // Today's receipts and deliveries
        $todayReceiptsCount = Picking::whereIn('company_id', $activeCompanyIds)
            ->whereHas('operationType', fn($q) => $q->where('code', 'incoming'))
            ->where('state', Picking::STATE_ASSIGNED)
            ->count();

        $todayDeliveriesCount = Picking::whereIn('company_id', $activeCompanyIds)
            ->whereHas('operationType', fn($q) => $q->where('code', 'outgoing'))
            ->where('state', Picking::STATE_ASSIGNED)
            ->count();

        // Low stock products (quants that are 0 or negative)
        $lowStockCount = Quant::whereIn('company_id', $activeCompanyIds)
            ->where('quantity', '<=', 0)
            ->whereHas('location', fn($q) => $q->where('usage', 'internal'))
            ->distinct('product_id')
            ->count('product_id');

        $productCount = Product::whereIn('company_id', $activeCompanyIds)->where('active', true)->count();
        $lotCount     = Lot::whereIn('company_id', $activeCompanyIds)->count();
        $stockLines   = Quant::whereIn('company_id', $activeCompanyIds)
            ->whereHas('location', fn($q) => $q->where('usage', 'internal'))
            ->where('quantity', '>', 0)
            ->count();

        return view('inventory.dashboard', compact(
            'operationTypes',
            'replenishCount',
            'todayReceiptsCount',
            'todayDeliveriesCount',
            'lowStockCount',
            'productCount',
            'lotCount',
            'stockLines',
        ));
    }
}
