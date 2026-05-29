<?php

namespace App\Http\Controllers\Inventory\Configuration;

use App\Helpers\GroupsQuery;
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

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(ProductCategory::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with('parent')->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('inventory.configuration.product-categories.index', compact('groups'));
            }
        }

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
            // `closest_location` was removed from this list — it had no
            // implementation behind it and silently behaved as FIFO. Legacy
            // rows on disk are still rendered (see ProductCategory accessor),
            // but new writes must pick a strategy the engine actually runs.
            'removal_strategy' => ['required', 'in:fifo,lifo,fefo'],
            'costing_method'   => ['required', 'in:standard_price,average_cost,fifo'],
        ]);
        $data['active']      = true;
        $data['created_by']  = auth()->id();
        $data['updated_by']  = auth()->id();

        $category = DB::transaction(function () use ($data) {
            $cat = ProductCategory::create($data);
            $cat->updateCompleteName();
            $this->chatterService->logCreated($cat, __('inventory.chatter_label_category'));
            return $cat;
        });

        return redirect()->route('inventory.config.product-categories.show', $category)->with('success', __('inventory.created'));
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
            // `closest_location` was removed from this list — it had no
            // implementation behind it and silently behaved as FIFO. Legacy
            // rows on disk are still rendered (see ProductCategory accessor),
            // but new writes must pick a strategy the engine actually runs.
            'removal_strategy' => ['required', 'in:fifo,lifo,fefo'],
            'costing_method'   => ['required', 'in:standard_price,average_cost,fifo'],
        ]);

        // Reject hierarchy cycles (A→B→A). Walks up to 64 levels.
        if (array_key_exists('parent_id', $data) && $data['parent_id']) {
            $parentId = (int) $data['parent_id'];
            if ($parentId === $productCategory->id || $this->isCategoryDescendantOf($parentId, $productCategory->id)) {
                return back()->withInput()->with('error', __('inventory.parent_cycle_category'));
            }
        }

        $data['updated_by'] = auth()->id();
        DB::transaction(function () use ($productCategory, $data) {
            $productCategory->update($data);
            $productCategory->updateCompleteName();
        });
        return redirect()->route('inventory.config.product-categories.show', $productCategory)->with('success', __('inventory.updated'));
    }

    private function isCategoryDescendantOf(int $candidateAncestorId, int $rootId): bool
    {
        $cursor = $candidateAncestorId;
        for ($i = 0; $i < 64 && $cursor; $i++) {
            $parent = ProductCategory::where('id', $cursor)->value('parent_id');
            if ($parent === null) return false;
            if ((int) $parent === $rootId) return true;
            $cursor = (int) $parent;
        }
        return false;
    }

    public function unlink(Request $_request, ProductCategory $productCategory)
    {
        abort_unless($_request->user()->hasPermission('inventory.config'), 403);

        try {
            DB::transaction(function () use ($productCategory) {
                ProductCategory::whereKey($productCategory->id)->lockForUpdate()->firstOrFail();
                if ($productCategory->children()->exists()) {
                    throw new \RuntimeException(__('inventory.err_category_has_children'));
                }
                $productCategory->delete();
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()->route('inventory.config.product-categories.index')->with('success', __('inventory.deleted'));
    }
}
