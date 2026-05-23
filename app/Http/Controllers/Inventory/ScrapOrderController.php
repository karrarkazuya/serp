<?php

namespace App\Http\Controllers\Inventory;

use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreScrapOrderRequest;
use App\Models\Inventory\Location;
use App\Models\Inventory\ScrapOrder;
use App\Services\Company\CompanyContextService;
use App\Services\Inventory\ScrapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScrapOrderController extends Controller
{
    public function __construct(
        private readonly ScrapService $scrapService,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', ScrapOrder::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = ScrapOrder::query()->with(['product', 'location', 'lot'])->forCompanies($activeCompanyIds);

        SearchFilters::apply($query, $request);
        SortsTable::apply($query, $request);

        $scrapOrders = $query->paginate(24)->withQueryString();

        return view('inventory.scrap.index', compact('scrapOrders'));
    }

    public function show(ScrapOrder $scrapOrder)
    {
        $this->authorize('view', $scrapOrder);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($scrapOrder->company_id, $activeCompanyIds), 403);
        $scrapOrder->load(['product.uom', 'location', 'scrapLocation', 'lot', 'picking', 'creator', 'updater']);

        $allIds = ScrapOrder::forCompanies($activeCompanyIds)->orderBy('name')->pluck('id');
        $currentIndex   = $allIds->search($scrapOrder->id);
        $prevId         = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId         = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal    = $allIds->count();

        return view('inventory.scrap.show', compact('scrapOrder', 'prevId', 'nextId', 'recordPosition', 'recordTotal'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', ScrapOrder::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;
        $scrapLocations   = Location::where('scrap_location', true)->where('active', true)->get();
        return view('inventory.scrap.create', compact('defaultCompanyId', 'scrapLocations'));
    }

    public function store(StoreScrapOrderRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $scrap = DB::transaction(fn () => $this->scrapService->create($data));
        return redirect()->route('inventory.scrap.show', $scrap)->with('success', 'Scrap order created.');
    }

    public function validateScrap(Request $_request, ScrapOrder $scrapOrder)
    {
        $this->authorize('update', $scrapOrder);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($scrapOrder->company_id, $activeCompanyIds), 403);
        abort_if($scrapOrder->isDone(), 403, 'Already validated.');
        DB::transaction(fn () => $this->scrapService->validate($scrapOrder));
        return redirect()->route('inventory.scrap.show', $scrapOrder)->with('success', 'Scrap order validated.');
    }

    public function unlink(Request $_request, ScrapOrder $scrapOrder)
    {
        $this->authorize('delete', $scrapOrder);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($scrapOrder->company_id, $activeCompanyIds), 403);
        try {
            DB::transaction(fn () => $this->scrapService->delete($scrapOrder));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()->route('inventory.scrap.index')->with('success', 'Scrap order deleted.');
    }

    public function addComment(Request $request, ScrapOrder $scrapOrder)
    {
        $this->authorize('comment', $scrapOrder);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($scrapOrder->company_id, $activeCompanyIds), 403);
        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $scrapOrder->logComment($request->body));
        return back()->with('success', 'Comment added.');
    }
}
