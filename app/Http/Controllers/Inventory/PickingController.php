<?php

namespace App\Http\Controllers\Inventory;

use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StorePickingRequest;
use App\Http\Requests\Inventory\UpdatePickingRequest;
use App\Models\Inventory\Move;
use App\Models\Inventory\OperationType;
use App\Models\Inventory\Picking;
use App\Services\Company\CompanyContextService;
use App\Services\Inventory\PickingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PickingController extends Controller
{
    public function __construct(
        private readonly PickingService $pickingService,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Picking::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = Picking::query()->with(['operationType', 'partner', 'srcLocation', 'destLocation']);
        $query->forCompanies($activeCompanyIds);

        // Filter by operation type code (receipts/deliveries/internal)
        if ($code = $request->query('type')) {
            $query->whereHas('operationType', fn($q) => $q->where('code', $code));
        }

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'all') {
            // no filter
        } elseif ($state = $request->query('state')) {
            $query->where('state', $state);
        } else {
            $query->whereIn('state', [Picking::STATE_DRAFT, Picking::STATE_CONFIRMED, Picking::STATE_ASSIGNED]);
        }

        SortsTable::apply($query, $request);

        $pickings = $query->paginate(24)->withQueryString();
        $typeCode = $request->query('type');
        $title    = match($typeCode) {
            'incoming' => 'Receipts',
            'outgoing' => 'Deliveries',
            'internal' => 'Internal Transfers',
            default    => 'All Transfers',
        };

        return view('inventory.transfers.index', compact('pickings', 'typeCode', 'title'));
    }

    public function show(Picking $picking)
    {
        $this->authorize('view', $picking);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($picking->company_id, $activeCompanyIds), 403);

        $picking->load([
            'operationType', 'partner', 'srcLocation', 'destLocation',
            'moves.product.uom', 'moves.srcLocation', 'moves.destLocation', 'moves.moveLines.lot',
            'creator', 'updater',
        ]);

        $allIds = Picking::forCompanies($activeCompanyIds)->orderBy('name')->pluck('id');
        $currentIndex   = $allIds->search($picking->id);
        $prevId         = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId         = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal    = $allIds->count();

        return view('inventory.transfers.show', compact(
            'picking', 'prevId', 'nextId', 'recordPosition', 'recordTotal'
        ));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Picking::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;

        // Pre-select operation type from query
        $operationType = null;
        if ($typeId = $request->query('operation_type_id')) {
            $operationType = OperationType::find($typeId);
        }

        $operationTypes = OperationType::whereIn('company_id', $activeCompanyIds)->where('active', true)->orderBy('name')->get();

        return view('inventory.transfers.create', compact('defaultCompanyId', 'operationType', 'operationTypes'));
    }

    public function store(StorePickingRequest $request)
    {
        $data      = $request->validated();
        $movesData = $data['moves'] ?? [];
        unset($data['moves']);

        $data['active']      = true;
        $data['created_by']  = auth()->id();
        $data['updated_by']  = auth()->id();

        foreach ($movesData as &$m) {
            $m['created_by'] = auth()->id();
            $m['updated_by'] = auth()->id();
        }

        $picking = DB::transaction(fn () => $this->pickingService->create($data, $movesData));

        return redirect()->route('inventory.transfers.show', $picking)->with('success', 'Transfer created.');
    }

    public function edit(Picking $picking)
    {
        $this->authorize('update', $picking);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($picking->company_id, $activeCompanyIds), 403);
        abort_if($picking->isDone() || $picking->isCancelled(), 403, 'Cannot edit a done or cancelled transfer.');

        $picking->load(['operationType', 'partner', 'srcLocation', 'destLocation', 'moves.product.uom']);

        return view('inventory.transfers.edit', compact('picking'));
    }

    public function write(UpdatePickingRequest $request, Picking $picking)
    {
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($picking->company_id, $activeCompanyIds), 403);
        abort_if($picking->isDone() || $picking->isCancelled(), 403);

        $data      = $request->validated();
        $movesData = $data['moves'] ?? [];
        $linesData = $data['move_lines'] ?? [];
        unset($data['moves'], $data['move_lines']);

        DB::transaction(function () use ($picking, $data, $movesData, $linesData) {
            $picking->update(array_merge($data, ['updated_by' => auth()->id()]));

            // Sync moves
            $existingMoveIds = [];
            foreach ($movesData as $moveRow) {
                if (!empty($moveRow['delete'])) {
                    if (!empty($moveRow['id'])) {
                        $move = Move::find($moveRow['id']);
                        if ($move && $move->picking_id === $picking->id) {
                            $this->pickingService->releaseMoveReservation($move);
                            $move->delete();
                        }
                    }
                    continue;
                }
                if (!empty($moveRow['id'])) {
                    $move = Move::find($moveRow['id']);
                    if ($move && $move->picking_id === $picking->id) {
                        $move->update([
                            'product_qty' => $moveRow['product_qty'],
                            'qty_done'    => $moveRow['qty_done'] ?? $move->qty_done,
                            'sequence'    => $moveRow['sequence'] ?? $move->sequence,
                            'updated_by'  => auth()->id(),
                        ]);
                        $existingMoveIds[] = $move->id;
                    }
                } else {
                    $move = $this->pickingService->addMove($picking, $moveRow);
                    $existingMoveIds[] = $move->id;
                }
            }

            // Sync move lines
            foreach ($linesData as $lineRow) {
                $move = Move::find($lineRow['move_id']);
                if ($move && $move->picking_id === $picking->id) {
                    $this->pickingService->addMoveLine($move, $lineRow);
                }
            }
        });

        return redirect()->route('inventory.transfers.show', $picking)->with('success', 'Transfer updated.');
    }

    public function confirm(Request $_request, Picking $picking)
    {
        $this->authorize('update', $picking);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($picking->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $this->pickingService->confirm($picking));
        return back()->with('success', 'Transfer confirmed.');
    }

    public function checkAvailability(Request $_request, Picking $picking)
    {
        $this->authorize('update', $picking);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($picking->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $this->pickingService->checkAvailability($picking));
        return back()->with('success', 'Availability checked.');
    }

    public function validate(Request $request, Picking $picking)
    {
        $this->authorize('validate', $picking);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($picking->company_id, $activeCompanyIds), 403);

        $picking->loadMissing('operationType');

        $doneQties = [];
        foreach ($request->input('qty_done', []) as $moveId => $qty) {
            $doneQties[(int) $moveId] = (float) $qty;
        }

        $createBackorder = !$request->boolean('no_backorder');

        try {
            $result = DB::transaction(fn () => $this->pickingService->validate($picking, $doneQties, $createBackorder));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($result['backorder']) {
            return redirect()->route('inventory.transfers.show', $result['backorder'])
                ->with('success', 'Transfer validated. A backorder was created for the remaining quantities.');
        }

        return redirect()->route('inventory.transfers.show', $result['picking'])
            ->with('success', 'Transfer validated successfully.');
    }

    public function cancel(Request $_request, Picking $picking)
    {
        $this->authorize('update', $picking);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($picking->company_id, $activeCompanyIds), 403);

        try {
            DB::transaction(fn () => $this->pickingService->cancel($picking));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transfer cancelled.');
    }

    public function returnPicking(Request $request, Picking $picking)
    {
        $this->authorize('create', Picking::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($picking->company_id, $activeCompanyIds), 403);
        abort_unless($picking->isDone(), 403, 'Can only return done transfers.');

        $returnQties = [];
        foreach ($request->input('return_qty', []) as $moveId => $qty) {
            $returnQties[(int) $moveId] = (float) $qty;
        }

        try {
            $returnPicking = DB::transaction(fn () => $this->pickingService->createReturn($picking, $returnQties));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('inventory.transfers.show', $returnPicking)->with('success', 'Return transfer created.');
    }

    public function unlink(Request $_request, Picking $picking)
    {
        $this->authorize('delete', $picking);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($picking->company_id, $activeCompanyIds), 403);
        abort_if($picking->isDone(), 403, 'Cannot delete a done transfer.');
        DB::transaction(function () use ($picking) {
            $this->pickingService->releasePickingReservations($picking);
            $picking->moveLines()->delete();
            $picking->moves()->delete();
            $picking->delete();
        });
        return redirect()->route('inventory.transfers.index')->with('success', 'Transfer deleted.');
    }

    public function addComment(Request $request, Picking $picking)
    {
        $this->authorize('comment', $picking);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($picking->company_id, $activeCompanyIds), 403);
        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $picking->logComment($request->body));
        return back()->with('success', 'Comment added.');
    }
}
