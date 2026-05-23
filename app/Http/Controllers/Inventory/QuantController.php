<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Location;
use App\Models\Inventory\Product;
use App\Models\Inventory\Quant;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;

class QuantController extends Controller
{
    public function __construct(private readonly CompanyContextService $companyContext) {}

    public function read(Request $request)
    {
        abort_unless($request->user()->hasPermission('inventory.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        $query = Quant::query()
            ->with(['product.uom', 'location', 'lot'])
            ->forCompanies($activeCompanyIds)
            ->whereHas('location', fn($q) => $q->where('usage', 'internal')->where('active', true));

        if ($productId = $request->query('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($locationId = $request->query('location_id')) {
            $query->where('location_id', $locationId);
        }

        // Only show non-zero by default
        if ($request->query('show_zero') !== '1') {
            $query->where('quantity', '>', 0);
        }

        $quants = $query->orderBy('location_id')->orderBy('product_id')->paginate(50)->withQueryString();

        return view('inventory.reports.stock', compact('quants'));
    }
}
