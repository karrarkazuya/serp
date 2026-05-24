<?php

namespace App\Http\Controllers\Inventory;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Models\Inventory\Lot;
use App\Services\Chatter\ChatterService;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LotController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly ChatterService $chatterService,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Lot::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = Lot::query()->with(['product', 'company']);
        $query->forCompanies($activeCompanyIds);

        if ($request->query('filter') === 'archived') {
            $query->inactive();
        } else {
            $query->active();
        }

        SearchFilters::apply($query, $request);

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(Lot::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with(['product', 'company'])->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('inventory.lots.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);

        $lots = $query->paginate(24)->withQueryString();

        return view('inventory.lots.index', compact('lots'));
    }

    public function show(Lot $lot)
    {
        $this->authorize('view', $lot);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($lot->company_id, $activeCompanyIds), 403);

        $lot->load(['product', 'quants.location', 'creator', 'updater']);

        $allIds = Lot::active()->forCompanies($activeCompanyIds)->orderBy('name')->pluck('id');
        $currentIndex   = $allIds->search($lot->id);
        $prevId         = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId         = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal    = $allIds->count();

        return view('inventory.lots.show', compact('lot', 'prevId', 'nextId', 'recordPosition', 'recordTotal'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Lot::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;
        $productId = $request->query('product_id');
        return view('inventory.lots.create', compact('defaultCompanyId', 'productId'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Lot::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        // Scope FKs at the validation layer (not after the fact) — a Lot stamped
        // company_id=A but pointing at a company-B product would surface in any
        // "lots of product X" dropdown and contaminate downstream picking flows.
        $companyRule = \Illuminate\Validation\Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds);
        $productRule = \Illuminate\Validation\Rule::exists('inventory_products', 'id')->where(function ($q) use ($activeCompanyIds) {
            if (empty($activeCompanyIds)) { $q->whereRaw('1 = 0'); return; }
            $q->whereIn('company_id', $activeCompanyIds)->orWhereNull('company_id');
        });

        $data = $request->validate([
            'company_id'      => ['required', $companyRule],
            'product_id'      => ['required', $productRule],
            'name'            => ['required', 'string', 'max:128'],
            'ref'             => ['nullable', 'string', 'max:128'],
            'expiration_date' => ['nullable', 'date'],
            'use_date'        => ['nullable', 'date'],
            'removal_date'    => ['nullable', 'date'],
            'note'            => ['nullable', 'string'],
        ]);

        $data['active']      = true;
        $data['created_by']  = auth()->id();
        $data['updated_by']  = auth()->id();

        $lot = DB::transaction(function () use ($data) {
            $lot = Lot::create($data);
            $this->chatterService->logCreated($lot, 'Lot');
            return $lot;
        });

        return redirect()->route('inventory.lots.show', $lot)->with('success', 'Lot created.');
    }

    public function edit(Lot $lot)
    {
        $this->authorize('update', $lot);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($lot->company_id, $activeCompanyIds), 403);
        $lot->load(['product']);
        return view('inventory.lots.edit', compact('lot'));
    }

    public function write(Request $request, Lot $lot)
    {
        $this->authorize('update', $lot);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($lot->company_id, $activeCompanyIds), 403);

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:128'],
            'ref'             => ['nullable', 'string', 'max:128'],
            'expiration_date' => ['nullable', 'date'],
            'use_date'        => ['nullable', 'date'],
            'removal_date'    => ['nullable', 'date'],
            'note'            => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($lot, $data) {
            $lot->update(array_merge($data, ['updated_by' => auth()->id()]));
            $this->chatterService->logUpdated($lot, [], 'Lot');
        });

        return redirect()->route('inventory.lots.show', $lot)->with('success', 'Lot updated.');
    }

    public function unlink(Request $_request, Lot $lot)
    {
        $this->authorize('delete', $lot);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($lot->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $lot->delete());
        return redirect()->route('inventory.lots.index')->with('success', 'Lot deleted.');
    }

    public function addComment(Request $request, Lot $lot)
    {
        $this->authorize('comment', $lot);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($lot->company_id, $activeCompanyIds), 403);
        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $lot->logComment($request->body));
        return back()->with('success', 'Comment added.');
    }
}
