<?php

namespace App\Http\Controllers\Inventory\Configuration;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Models\Inventory\PutawayRule;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PutawayRuleController extends Controller
{
    public function __construct(private readonly CompanyContextService $companyContext) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', PutawayRule::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = PutawayRule::query()->with(['company', 'location', 'fixedLocation', 'product', 'productCategory'])->forCompanies($activeCompanyIds);
        SearchFilters::apply($query, $request);

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(PutawayRule::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with(['company', 'location', 'fixedLocation', 'product', 'productCategory'])->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('inventory.configuration.putaway-rules.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);
        $putawayRules = $query->paginate(24)->withQueryString();
        return view('inventory.configuration.putaway-rules.index', compact('putawayRules'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', PutawayRule::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;
        return view('inventory.configuration.putaway-rules.create', compact('defaultCompanyId'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', PutawayRule::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $data = $request->validate([
            'company_id'          => ['required', 'exists:companies,id'],
            'location_id'         => ['required', 'exists:inventory_locations,id'],
            'fixed_location_id'   => ['required', 'exists:inventory_locations,id'],
            'product_id'          => ['nullable', 'exists:inventory_products,id'],
            'product_category_id' => ['nullable', 'exists:inventory_product_categories,id'],
            'sequence'            => ['nullable', 'integer', 'min:0'],
        ]);
        abort_unless(in_array($data['company_id'], $activeCompanyIds), 403);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        DB::transaction(fn () => PutawayRule::create($data));
        return redirect()->route('inventory.config.putaway-rules.index')->with('success', 'Putaway rule created.');
    }

    public function edit(PutawayRule $putawayRule)
    {
        $this->authorize('update', $putawayRule);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($putawayRule->company_id, $activeCompanyIds), 403);
        $putawayRule->load(['location', 'fixedLocation', 'product', 'productCategory']);
        return view('inventory.configuration.putaway-rules.edit', compact('putawayRule'));
    }

    public function write(Request $request, PutawayRule $putawayRule)
    {
        $this->authorize('update', $putawayRule);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($putawayRule->company_id, $activeCompanyIds), 403);
        $data = $request->validate([
            'location_id'         => ['required', 'exists:inventory_locations,id'],
            'fixed_location_id'   => ['required', 'exists:inventory_locations,id'],
            'product_id'          => ['nullable', 'exists:inventory_products,id'],
            'product_category_id' => ['nullable', 'exists:inventory_product_categories,id'],
            'sequence'            => ['nullable', 'integer', 'min:0'],
        ]);
        $data['updated_by'] = auth()->id();
        DB::transaction(fn () => $putawayRule->update($data));
        return redirect()->route('inventory.config.putaway-rules.index')->with('success', 'Putaway rule updated.');
    }

    public function unlink(Request $_request, PutawayRule $putawayRule)
    {
        $this->authorize('delete', $putawayRule);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($putawayRule->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $putawayRule->delete());
        return redirect()->route('inventory.config.putaway-rules.index')->with('success', 'Putaway rule deleted.');
    }
}
