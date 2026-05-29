<?php

namespace App\Http\Controllers\Inventory\Configuration;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\Concerns\InventoryFkRules;
use App\Models\Inventory\Route;
use App\Models\Inventory\RouteRule;
use App\Services\Chatter\ChatterService;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RouteController extends Controller
{
    use InventoryFkRules;

    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly ChatterService $chatterService,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Route::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = Route::query()->with('company')->forCompanies($activeCompanyIds);
        if ($request->query('filter') !== 'all') $query->active();
        SearchFilters::apply($query, $request);

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(Route::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with('company')->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('inventory.configuration.routes.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);
        $routes = $query->paginate(24)->withQueryString();
        return view('inventory.configuration.routes.index', compact('routes'));
    }

    public function show(Route $route)
    {
        $this->authorize('view', $route);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($route->company_id, $activeCompanyIds), 403);
        $route->load(['company', 'rules.operationType', 'rules.sourceLocation', 'rules.destLocation', 'creator', 'updater']);

        $allIds = Route::active()->forCompanies($activeCompanyIds)->orderBy('name')->pluck('id');
        $currentIndex   = $allIds->search($route->id);
        $prevId         = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId         = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal    = $allIds->count();

        return view('inventory.configuration.routes.show', compact(
            'route', 'prevId', 'nextId', 'recordPosition', 'recordTotal'
        ));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Route::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;
        return view('inventory.configuration.routes.create', compact('defaultCompanyId'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Route::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        // Rule 11: warehouse FKs must stay in the actor's active companies.
        // A route that lists a Company B warehouse as supplier_wh / supplied_wh
        // would silently generate cross-tenant replenishment pickings when the
        // route fires.
        $warehouseRule = $this->companyScopedExists('inventory_warehouses', $activeCompanyIds);

        $data = $request->validate([
            'company_id'        => ['required', Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds)],
            'name'              => ['required', 'string', 'max:255'],
            'supplied_wh_id'    => ['nullable', $warehouseRule],
            'supplier_wh_id'    => ['nullable', $warehouseRule],
        ]);
        $data['active']     = true;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $route = DB::transaction(function () use ($data) {
            $r = Route::create($data);
            $this->chatterService->logCreated($r, __('inventory.chatter_label_route'));
            return $r;
        });
        return redirect()->route('inventory.config.routes.show', $route)->with('success', __('inventory.created'));
    }

    public function newRuleRow(Request $request): \Illuminate\View\View
    {
        $this->authorize('update', Route::class);
        $idx = max(0, (int) $request->query('idx', 0));
        return view('inventory.configuration.routes._rule-row', compact('idx'));
    }

    public function edit(Route $route)
    {
        $this->authorize('update', $route);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($route->company_id, $activeCompanyIds), 403);
        $route->load(['rules.operationType', 'rules.sourceLocation', 'rules.destLocation']);
        return view('inventory.configuration.routes.edit', compact('route'));
    }

    public function write(Request $request, Route $route)
    {
        $this->authorize('update', $route);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($route->company_id, $activeCompanyIds), 403);

        // Rule 11: every FK in the route + its nested rules must stay inside
        // the actor's active companies. Without this, an editor could repoint
        // the route at Company B warehouses or stitch in rules that source/sink
        // stock at Company B locations — the route engine would later move
        // stock across tenant boundaries when the rule fires.
        $warehouseRule  = $this->companyScopedExists('inventory_warehouses',      $activeCompanyIds);
        $opTypeRule     = $this->companyScopedExists('inventory_operation_types', $activeCompanyIds);
        $locationRule   = $this->inventoryLocationRule($activeCompanyIds);
        $ruleScopedRule = Rule::exists('inventory_route_rules', 'id')->where(function ($q) use ($route) {
            // Nested rule ids must belong to THIS route — prevents pulling
            // another route's rule rows into the update by id-injection.
            $q->where('route_id', $route->id);
        });

        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'supplied_wh_id'    => ['nullable', $warehouseRule],
            'supplier_wh_id'    => ['nullable', $warehouseRule],
            'rules'             => ['nullable', 'array'],
            'rules.*.id'        => ['nullable', $ruleScopedRule],
            'rules.*.name'                    => ['required_without:rules.*.delete', 'string', 'max:255'],
            'rules.*.operation_type_id'       => ['required_without:rules.*.delete', $opTypeRule],
            'rules.*.source_location_id'      => ['nullable', $locationRule],
            'rules.*.destination_location_id' => ['nullable', $locationRule],
            'rules.*.action'                  => ['required_without:rules.*.delete', 'in:pull,push,pull_push'],
            'rules.*.sequence'                => ['nullable', 'integer'],
            'rules.*.delete'                  => ['nullable', 'boolean'],
        ]);
        $data['updated_by'] = auth()->id();
        DB::transaction(function () use ($route, $data) {
            $route->update(['name' => $data['name'], 'supplied_wh_id' => $data['supplied_wh_id'] ?? null, 'supplier_wh_id' => $data['supplier_wh_id'] ?? null, 'updated_by' => $data['updated_by']]);
            foreach ($data['rules'] ?? [] as $ruleData) {
                if (!empty($ruleData['delete']) && !empty($ruleData['id'])) {
                    RouteRule::where('id', $ruleData['id'])->where('route_id', $route->id)->delete();
                    continue;
                }
                if (!empty($ruleData['id'])) {
                    RouteRule::where('id', $ruleData['id'])->where('route_id', $route->id)->update([
                        'name'                   => $ruleData['name'],
                        'operation_type_id'      => $ruleData['operation_type_id'],
                        'source_location_id'     => $ruleData['source_location_id'] ?? null,
                        'destination_location_id'=> $ruleData['destination_location_id'] ?? null,
                        'action'                 => $ruleData['action'],
                        'sequence'               => $ruleData['sequence'] ?? 20,
                    ]);
                } else {
                    $route->rules()->create([
                        'name'                   => $ruleData['name'],
                        'operation_type_id'      => $ruleData['operation_type_id'],
                        'source_location_id'     => $ruleData['source_location_id'] ?? null,
                        'destination_location_id'=> $ruleData['destination_location_id'] ?? null,
                        'action'                 => $ruleData['action'],
                        'sequence'               => $ruleData['sequence'] ?? 20,
                        'company_id'             => $route->company_id,
                    ]);
                }
            }
        });
        return redirect()->route('inventory.config.routes.show', $route)->with('success', __('inventory.updated'));
    }

    public function archive(Request $_request, Route $route)
    {
        $this->authorize('update', $route);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($route->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $route->update(['active' => false, 'updated_by' => auth()->id()]));
        return redirect()->route('inventory.config.routes.index')->with('success', __('inventory.archived'));
    }

    public function unarchive(Request $_request, Route $route)
    {
        $this->authorize('update', $route);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($route->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $route->update(['active' => true, 'updated_by' => auth()->id()]));
        return redirect()->route('inventory.config.routes.show', $route)->with('success', __('inventory.restored'));
    }

    public function unlink(Request $_request, Route $route)
    {
        $this->authorize('delete', $route);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($route->company_id, $activeCompanyIds), 403);
        DB::transaction(function () use ($route) {
            $route->rules()->delete();
            $route->delete();
        });
        return redirect()->route('inventory.config.routes.index')->with('success', __('inventory.deleted'));
    }
}
