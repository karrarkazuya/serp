<?php

namespace App\Http\Controllers\Inventory;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreProductRequest;
use App\Http\Requests\Inventory\UpdateProductRequest;
use App\Models\Inventory\Product;
use App\Models\Inventory\Uom;
use App\Services\Company\CompanyContextService;
use App\Services\FileService;
use App\Services\Inventory\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly CompanyContextService $companyContext,
        private readonly FileService $fileService,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = Product::query()->with(['category', 'uom', 'company']);
        $query->forCompanies($activeCompanyIds);

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->inactive();
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        if ($type = $request->query('product_type')) {
            $query->where('product_type', $type);
        }

        $view = $request->query('view', 'kanban');

        if ($view === 'list') {
            $groupBy = $request->query('group_by');
            if ($groupBy) {
                $fields = SearchFilters::fieldsFor(Product::class);
                if (isset($fields[$groupBy])) {
                    $records = (clone $query)->with(['category', 'uom', 'company'])->orderBy('id')->get();
                    $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                    return view('inventory.products.index', compact('groups', 'view'));
                }
            }
        }

        SortsTable::apply($query, $request);

        $products = $query->paginate(24)->withQueryString();

        return view('inventory.products.index', compact('products', 'view'));
    }

    public function show(Product $product)
    {
        $this->authorize('view', $product);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($product->company_id, $activeCompanyIds) || is_null($product->company_id), 403);

        $product->load(['category', 'uom', 'uomPo', 'company', 'suppliers.partner', 'routes', 'creator', 'updater']);

        $allIds = Product::active()->forCompanies($activeCompanyIds)->orderBy('name')->pluck('id');
        $currentIndex   = $allIds->search($product->id);
        $prevId         = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId         = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal    = $allIds->count();

        // On-hand stock by location (scoped to active companies)
        $quants = $product->quants()
            ->with('location', 'lot')
            ->whereIn('company_id', $activeCompanyIds)
            ->whereHas('location', fn($q) => $q->where('usage', 'internal'))
            ->orderBy('location_id')
            ->get();

        return view('inventory.products.show', compact(
            'product', 'prevId', 'nextId', 'recordPosition', 'recordTotal', 'quants'
        ));
    }

    public function uomInfo(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $productId = (int) $request->query('product_id');
        $product   = Product::with('uom')->findOrFail($productId);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($product->company_id, $activeCompanyIds) || is_null($product->company_id), 403);

        return response()->json([
            'uom_id'   => $product->uom_id,
            'uom_name' => $product->uom?->name ?? '',
        ]);
    }

    public function create(Request $_request)
    {
        $this->authorize('create', Product::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;
        $defaultUom       = Uom::where('uom_type', 'reference')->first();

        return view('inventory.products.create', compact('defaultCompanyId', 'defaultUom'));
    }

    public function store(StoreProductRequest $request)
    {
        $data       = $request->validated();
        $routeIds   = $data['routes'] ?? [];
        $suppliers  = $data['suppliers'] ?? [];
        // Unchecked HTML checkbox = no posted value. Without this, the box
        // could never be *un*checked on edit (it'd silently stay true forever).
        $data['has_expiration_date'] = $request->boolean('has_expiration_date');
        unset($data['routes'], $data['suppliers']);

        $imageRecord = null;
        if ($request->hasFile('image')) {
            $imageRecord     = $this->fileService->store($request->file('image'), 'inventory/products', 'inventory.read');
            $data['image_uuid'] = $imageRecord->uuid;
        }

        try {
            $product = DB::transaction(function () use ($data, $routeIds, $suppliers, $imageRecord) {
                $product = $this->productService->create($data);
                $product->routes()->sync($routeIds);
                $this->productService->syncSuppliers($product, $suppliers);
                $imageRecord?->update(['source_type' => $product->getTable(), 'source_id' => $product->id]);
                return $product;
            });
        } catch (\Throwable $e) {
            if (isset($data['image_uuid'])) {
                $this->fileService->deleteByUuid($data['image_uuid']);
            }
            throw $e;
        }

        return redirect()->route('inventory.products.show', $product)->with('success', __('inventory.created'));
    }

    public function edit(Product $product)
    {
        $this->authorize('update', $product);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($product->company_id, $activeCompanyIds) || is_null($product->company_id), 403);

        $product->load(['category', 'uom', 'uomPo', 'suppliers.partner', 'routes']);

        return view('inventory.products.edit', compact('product'));
    }

    public function write(UpdateProductRequest $request, Product $product)
    {
        $this->authorize('update', $product);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($product->company_id, $activeCompanyIds) || is_null($product->company_id), 403);

        $data      = $request->validated();
        $routeIds  = $data['routes'] ?? [];
        $suppliers = $data['suppliers'] ?? [];
        $data['has_expiration_date'] = $request->boolean('has_expiration_date');
        unset($data['routes'], $data['suppliers']);

        $oldImageUuid = $product->image_uuid;

        if ($request->hasFile('image')) {
            $imageRecord        = $this->fileService->store($request->file('image'), 'inventory/products', 'inventory.read', null, $product);
            $data['image_uuid'] = $imageRecord->uuid;
        }

        try {
            DB::transaction(function () use ($product, $data, $routeIds, $suppliers) {
                $this->productService->update($product, $data);
                $product->routes()->sync($routeIds);
                $this->productService->syncSuppliers($product, $suppliers);
            });
        } catch (\Throwable $e) {
            if (isset($data['image_uuid'])) $this->fileService->deleteByUuid($data['image_uuid']);
            throw $e;
        }

        if ($request->hasFile('image') && $oldImageUuid) {
            $this->fileService->deleteByUuid($oldImageUuid);
        }

        return redirect()->route('inventory.products.show', $product)->with('success', __('inventory.updated'));
    }

    public function archive(Request $_request, Product $product)
    {
        $this->authorize('update', $product);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($product->company_id, $activeCompanyIds) || is_null($product->company_id), 403);
        DB::transaction(fn () => $this->productService->archive($product));
        return redirect()->route('inventory.products.index')->with('success', __('inventory.archived'));
    }

    public function unarchive(Request $_request, Product $product)
    {
        $this->authorize('update', $product);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($product->company_id, $activeCompanyIds) || is_null($product->company_id), 403);
        DB::transaction(fn () => $this->productService->unarchive($product));
        return redirect()->route('inventory.products.show', $product)->with('success', __('inventory.restored'));
    }

    public function unlink(Request $_request, Product $product)
    {
        $this->authorize('delete', $product);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($product->company_id, $activeCompanyIds) || is_null($product->company_id), 403);
        $imageUuid = $product->image_uuid;
        DB::transaction(fn () => $this->productService->delete($product));
        if ($imageUuid) $this->fileService->deleteByUuid($imageUuid);
        return redirect()->route('inventory.products.index')->with('success', __('inventory.deleted'));
    }

    public function addComment(Request $request, Product $product)
    {
        $this->authorize('comment', $product);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($product->company_id, $activeCompanyIds) || is_null($product->company_id), 403);
        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $product->logComment($request->body));
        return back()->with('success', __('inventory.comment_added'));
    }
}
