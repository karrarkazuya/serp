<?php

namespace App\Http\Controllers\Inventory\Configuration;

use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Models\Inventory\Uom;
use App\Models\Inventory\UomCategory;
use App\Services\Chatter\ChatterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UomController extends Controller
{
    public function __construct(private readonly ChatterService $chatterService) {}

    public function read(Request $request)
    {
        abort_unless($request->user()->hasPermission('inventory.read'), 403);
        $query = Uom::query()->with('category');
        SearchFilters::apply($query, $request);
        SortsTable::apply($query, $request);
        $uoms = $query->paginate(24)->withQueryString();
        $categories = UomCategory::active()->orderBy('name')->get();
        return view('inventory.configuration.uoms.index', compact('uoms', 'categories'));
    }

    public function show(Uom $uom)
    {
        abort_unless(request()->user()->hasPermission('inventory.read'), 403);
        $uom->load(['category', 'creator', 'updater']);

        $allIds = Uom::active()->orderBy('name')->pluck('id');
        $currentIndex = $allIds->search($uom->id);
        $prevId = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal    = $allIds->count();

        return view('inventory.configuration.uoms.show', compact('uom', 'prevId', 'nextId', 'recordPosition', 'recordTotal'));
    }

    public function create()
    {
        abort_unless(request()->user()->hasPermission('inventory.config'), 403);
        return view('inventory.configuration.uoms.create');
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('inventory.config'), 403);
        $data = $request->validate([
            'uom_category_id' => ['required', 'exists:inventory_uom_categories,id'],
            'name'            => ['required', 'string', 'max:128'],
            'symbol'          => ['nullable', 'string', 'max:32'],
            'ratio'           => ['required', 'numeric', 'min:0.000001'],
            'rounding'        => ['required', 'numeric', 'min:0.000001'],
            'uom_type'        => ['required', 'in:reference,bigger,smaller'],
        ]);
        $data['active']     = true;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $uom = DB::transaction(function () use ($data) {
            $uom = Uom::create($data);
            $this->chatterService->logCreated($uom, 'Unit of Measure');
            return $uom;
        });
        return redirect()->route('inventory.config.uoms.show', $uom)->with('success', 'Unit of Measure created.');
    }

    public function edit(Uom $uom)
    {
        abort_unless(request()->user()->hasPermission('inventory.config'), 403);
        return view('inventory.configuration.uoms.edit', compact('uom'));
    }

    public function write(Request $request, Uom $uom)
    {
        abort_unless($request->user()->hasPermission('inventory.config'), 403);
        $data = $request->validate([
            'uom_category_id' => ['required', 'exists:inventory_uom_categories,id'],
            'name'            => ['required', 'string', 'max:128'],
            'symbol'          => ['nullable', 'string', 'max:32'],
            'ratio'           => ['required', 'numeric', 'min:0.000001'],
            'rounding'        => ['required', 'numeric', 'min:0.000001'],
            'uom_type'        => ['required', 'in:reference,bigger,smaller'],
        ]);
        $data['updated_by'] = auth()->id();
        DB::transaction(fn () => $uom->update($data));
        return redirect()->route('inventory.config.uoms.show', $uom)->with('success', 'Unit of Measure updated.');
    }

    public function unlink(Request $_request, Uom $uom)
    {
        abort_unless($_request->user()->hasPermission('inventory.config'), 403);
        DB::transaction(fn () => $uom->update(['active' => false, 'updated_by' => auth()->id()]));
        return redirect()->route('inventory.config.uoms.index')->with('success', 'Unit of Measure archived.');
    }
}
