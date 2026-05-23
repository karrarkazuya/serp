<?php

namespace App\Http\Controllers\Inventory\Configuration;

use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Models\Inventory\ProductCategory;
use App\Services\Chatter\ChatterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductCategoryController extends Controller
{
    public function __construct(private readonly ChatterService $chatterService) {}

    public function read(Request $request)
    {
        abort_unless($request->user()->hasPermission('inventory.read'), 403);
        $query = ProductCategory::query()->with('parent');
        SearchFilters::apply($query, $request);
        SortsTable::apply($query, $request);
        $categories = $query->paginate(24)->withQueryString();
        return view('inventory.configuration.product-categories.index', compact('categories'));
    }

    public function show(ProductCategory $productCategory)
    {
        abort_unless(request()->user()->hasPermission('inventory.read'), 403);
        $productCategory->load(['parent', 'children', 'creator', 'updater']);

        $allIds = ProductCategory::active()->orderBy('complete_name')->pluck('id');
        $currentIndex = $allIds->search($productCategory->id);
        $prevId = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal    = $allIds->count();

        return view('inventory.configuration.product-categories.show', compact(
            'productCategory', 'prevId', 'nextId', 'recordPosition', 'recordTotal'
        ));
    }

    public function create()
    {
        abort_unless(request()->user()->hasPermission('inventory.config'), 403);
        return view('inventory.configuration.product-categories.create');
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('inventory.config'), 403);
        $data = $request->validate([
            'parent_id'        => ['nullable', 'exists:inventory_product_categories,id'],
            'name'             => ['required', 'string', 'max:255'],
            'removal_strategy' => ['required', 'in:fifo,lifo,fefo,closest_location'],
            'costing_method'   => ['required', 'in:standard_price,average_cost,fifo'],
        ]);
        $data['active']      = true;
        $data['created_by']  = auth()->id();
        $data['updated_by']  = auth()->id();

        $category = DB::transaction(function () use ($data) {
            $cat = ProductCategory::create($data);
            $cat->updateCompleteName();
            $this->chatterService->logCreated($cat, 'Product Category');
            return $cat;
        });

        return redirect()->route('inventory.config.product-categories.show', $category)->with('success', 'Category created.');
    }

    public function edit(ProductCategory $productCategory)
    {
        abort_unless(request()->user()->hasPermission('inventory.config'), 403);
        $productCategory->load('parent');
        return view('inventory.configuration.product-categories.edit', compact('productCategory'));
    }

    public function write(Request $request, ProductCategory $productCategory)
    {
        abort_unless($request->user()->hasPermission('inventory.config'), 403);
        $data = $request->validate([
            'parent_id'        => ['nullable', 'exists:inventory_product_categories,id'],
            'name'             => ['required', 'string', 'max:255'],
            'removal_strategy' => ['required', 'in:fifo,lifo,fefo,closest_location'],
            'costing_method'   => ['required', 'in:standard_price,average_cost,fifo'],
        ]);
        $data['updated_by'] = auth()->id();
        DB::transaction(function () use ($productCategory, $data) {
            $productCategory->update($data);
            $productCategory->updateCompleteName();
        });
        return redirect()->route('inventory.config.product-categories.show', $productCategory)->with('success', 'Category updated.');
    }

    public function unlink(Request $_request, ProductCategory $productCategory)
    {
        abort_unless($_request->user()->hasPermission('inventory.config'), 403);
        if ($productCategory->children()->exists()) {
            return back()->with('error', 'Cannot delete a category with sub-categories.');
        }
        DB::transaction(fn () => $productCategory->delete());
        return redirect()->route('inventory.config.product-categories.index')->with('success', 'Category deleted.');
    }
}
