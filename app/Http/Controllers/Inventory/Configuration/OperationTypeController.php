<?php

namespace App\Http\Controllers\Inventory\Configuration;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Models\Inventory\OperationType;
use App\Services\Chatter\ChatterService;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperationTypeController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly ChatterService $chatterService,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', OperationType::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = OperationType::query()->with(['warehouse', 'company'])->forCompanies($activeCompanyIds);
        if ($request->query('filter') !== 'all') $query->active();
        if ($code = $request->query('code')) $query->where('code', $code);
        SearchFilters::apply($query, $request);

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(OperationType::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with(['warehouse', 'company'])->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('inventory.configuration.operation-types.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);
        $operationTypes = $query->paginate(24)->withQueryString();
        return view('inventory.configuration.operation-types.index', compact('operationTypes'));
    }

    public function show(OperationType $operationType)
    {
        $this->authorize('view', $operationType);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($operationType->company_id, $activeCompanyIds), 403);
        $operationType->load(['warehouse', 'company', 'defaultSrcLocation', 'defaultDestLocation', 'returnPickingType', 'creator', 'updater']);

        $allIds = OperationType::active()->forCompanies($activeCompanyIds)->orderBy('name')->pluck('id');
        $currentIndex   = $allIds->search($operationType->id);
        $prevId         = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId         = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal    = $allIds->count();

        return view('inventory.configuration.operation-types.show', compact(
            'operationType', 'prevId', 'nextId', 'recordPosition', 'recordTotal'
        ));
    }

    public function create(Request $request)
    {
        $this->authorize('create', OperationType::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;
        return view('inventory.configuration.operation-types.create', compact('defaultCompanyId'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', OperationType::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $data = $request->validate([
            'company_id'                  => ['required', 'exists:companies,id'],
            'warehouse_id'                => ['nullable', 'exists:inventory_warehouses,id'],
            'name'                        => ['required', 'string', 'max:255'],
            'code'                        => ['required', 'in:incoming,outgoing,internal'],
            'sequence_prefix'             => ['required', 'string', 'max:32'],
            'sequence_padding'            => ['required', 'integer', 'min:1', 'max:10'],
            'default_location_src_id'     => ['nullable', 'exists:inventory_locations,id'],
            'default_location_dest_id'    => ['nullable', 'exists:inventory_locations,id'],
            'return_picking_type_id'      => ['nullable', 'exists:inventory_operation_types,id'],
            'use_create_lots'             => ['boolean'],
            'use_existing_lots'           => ['boolean'],
            'show_entire_packs'           => ['boolean'],
        ]);
        abort_unless(in_array($data['company_id'], $activeCompanyIds), 403);
        $data['active']               = true;
        $data['sequence_next_number']  = 1;
        $data['created_by']           = auth()->id();
        $data['updated_by']           = auth()->id();
        $operationType = DB::transaction(function () use ($data) {
            $op = OperationType::create($data);
            $this->chatterService->logCreated($op, 'Operation Type');
            return $op;
        });
        return redirect()->route('inventory.config.operation-types.show', $operationType)->with('success', 'Operation type created.');
    }

    public function edit(OperationType $operationType)
    {
        $this->authorize('update', $operationType);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($operationType->company_id, $activeCompanyIds), 403);
        $operationType->load(['warehouse', 'defaultSrcLocation', 'defaultDestLocation', 'returnPickingType']);
        return view('inventory.configuration.operation-types.edit', compact('operationType'));
    }

    public function write(Request $request, OperationType $operationType)
    {
        $this->authorize('update', $operationType);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($operationType->company_id, $activeCompanyIds), 403);
        $data = $request->validate([
            'name'                        => ['required', 'string', 'max:255'],
            'sequence_prefix'             => ['required', 'string', 'max:32'],
            'sequence_padding'            => ['required', 'integer', 'min:1', 'max:10'],
            'default_location_src_id'     => ['nullable', 'exists:inventory_locations,id'],
            'default_location_dest_id'    => ['nullable', 'exists:inventory_locations,id'],
            'return_picking_type_id'      => ['nullable', 'exists:inventory_operation_types,id'],
            'use_create_lots'             => ['boolean'],
            'use_existing_lots'           => ['boolean'],
            'show_entire_packs'           => ['boolean'],
        ]);
        $data['updated_by'] = auth()->id();
        DB::transaction(fn () => $operationType->update($data));
        return redirect()->route('inventory.config.operation-types.show', $operationType)->with('success', 'Operation type updated.');
    }

    public function archive(Request $_request, OperationType $operationType)
    {
        $this->authorize('update', $operationType);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($operationType->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $operationType->update(['active' => false, 'updated_by' => auth()->id()]));
        return redirect()->route('inventory.config.operation-types.index')->with('success', 'Operation type archived.');
    }

    public function unarchive(Request $_request, OperationType $operationType)
    {
        $this->authorize('update', $operationType);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($operationType->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $operationType->update(['active' => true, 'updated_by' => auth()->id()]));
        return redirect()->route('inventory.config.operation-types.show', $operationType)->with('success', 'Operation type restored.');
    }

    public function unlink(Request $_request, OperationType $operationType)
    {
        $this->authorize('delete', $operationType);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($operationType->company_id, $activeCompanyIds), 403);

        // Wrap usage check + delete in a single transaction with a parent-row
        // lock so a concurrent picking create can't slip past the check and
        // end up orphaned (pickings.operation_type_id is nullOnDelete).
        try {
            DB::transaction(function () use ($operationType) {
                OperationType::whereKey($operationType->id)->lockForUpdate()->firstOrFail();
                if ($operationType->pickings()->exists()) {
                    throw new \RuntimeException('Cannot delete an operation type with existing transfers.');
                }
                $operationType->delete();
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()->route('inventory.config.operation-types.index')->with('success', 'Operation type deleted.');
    }
}
