<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\StoreProcedureRequest;
use App\Models\Employees\Department;
use App\Models\Workflow\Procedure;
use App\Models\Workflow\ProcedureTemplate;
use App\Models\Workflow\Ticket;
use App\Models\Workflow\TicketPath;
use App\Models\Workflow\WorkflowRecordInput;
use App\Models\Workflow\WorkflowTemplateInputOption;
use App\Models\Workflow\WorkflowUser;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Services\Company\CompanyContextService;
use App\Services\Workflow\ProcedureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcedureController extends Controller
{
    public function __construct(
        private readonly ProcedureService $procedureService,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Procedure::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = Procedure::query()
            ->with(['procedureTemplate', 'createdByUser'])
            ->forUser(\Illuminate\Support\Facades\Auth::user());

        if (!empty($activeCompanyIds)) {
            $query->where(fn ($q) => $q->whereIn('company_id', $activeCompanyIds)->orWhereNull('company_id'));
        }

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->where('active', false);
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->where('active', true);
        }

        if ($state = $request->query('state')) {
            $query->where('state', $state);
        }

        $view = $request->query('view', 'list');

        if ($view === 'list') {
            $groupBy = $request->query('group_by');
            if ($groupBy) {
                $fields = SearchFilters::fieldsFor(Procedure::class);
                if (isset($fields[$groupBy])) {
                    $records = (clone $query)->with(['procedureTemplate', 'createdByUser'])->orderBy('id')->get();
                    $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                    return view('workflow.procedures.index', compact('groups', 'view'));
                }
            }
        }

        SortsTable::apply($query, $request);

        $procedures = $query->paginate(24)->withQueryString();

        return view('workflow.procedures.index', compact('procedures', 'view'));
    }

    public function show(Procedure $procedure)
    {
        $this->authorize('view', $procedure);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($procedure->company_id) || in_array($procedure->company_id, $activeCompanyIds), 403);

        $procedure->load([
            'procedureTemplate',
            'createdByUser',
            'optionalTicket',
            'creator',
            'updater',
            'sharedLink',
            'tickets' => fn ($q) => $q->orderBy('id'),
            'tickets.assignedDepartment',
            'tickets.assignedUser',
            'tickets.inputs.templateInput',
            'tickets.pathChoices',
            'tickets.nextTickets',
            'tickets.sharedLink',
        ]);
        return view('workflow.procedures.show', compact('procedure'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Procedure::class);

        $wu = WorkflowUser::where('user_id', auth()->id())->where('active', true)->first();
        $templates = ProcedureTemplate::where('enabled', true)->where('active', true)
            ->when($wu, fn ($q) => $q->visibleTo($wu))
            ->with('steps')
            ->orderBy('name')->get();
        $departments = Department::where('active', true)->orderBy('name')->get();
        $selectedTemplate = $request->query('template_id')
            ? ProcedureTemplate::find($request->query('template_id'))
            : null;

        return view('workflow.procedures.create', compact('templates', 'departments', 'selectedTemplate'));
    }

    public function store(StoreProcedureRequest $request)
    {
        $data = $request->validated();

        $template = ProcedureTemplate::where('id', $data['procedure_template_id'])
            ->where('enabled', true)
            ->where('active', true)
            ->firstOrFail();

        $wu = WorkflowUser::where('user_id', auth()->id())->where('active', true)->first();
        if ($wu && !ProcedureTemplate::where('id', $template->id)->visibleTo($wu)->exists()) {
            abort(403, 'You do not have access to this template.');
        }

        unset($data['procedure_template_id']);

        $procedure = DB::transaction(fn () => $this->procedureService->create($data, $template));

        return redirect()->route('workflow.procedures.show', $procedure)->with('success', 'Procedure started.');
    }

    public function archive(Procedure $procedure)
    {
        $this->authorize('update', $procedure);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($procedure->company_id) || in_array($procedure->company_id, $activeCompanyIds), 403);
        abort_if($procedure->state === 'pending', 403, 'Cannot archive a running procedure.');
        DB::transaction(fn () => $this->procedureService->archive($procedure));

        return redirect()->route('workflow.procedures.index')->with('success', 'Procedure archived.');
    }

    public function unarchive(Procedure $procedure)
    {
        $this->authorize('update', $procedure);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($procedure->company_id) || in_array($procedure->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $this->procedureService->unarchive($procedure));

        return back()->with('success', 'Procedure restored.');
    }

    public function unlink(Procedure $procedure)
    {
        $this->authorize('delete', $procedure);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($procedure->company_id) || in_array($procedure->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $this->procedureService->delete($procedure));

        return redirect()->route('workflow.procedures.index')->with('success', 'Procedure deleted.');
    }

    public function addComment(Request $request, Procedure $procedure)
    {
        $this->authorize('comment', $procedure);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($procedure->company_id) || in_array($procedure->company_id, $activeCompanyIds), 403);
        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $procedure->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }

    // Ticket state transitions live on the ticket page (TicketController).
    // The procedure side has no endpoints for complete/reject/skip — those
    // were 403 stubs and were removed.

    public function saveTicketInputs(Request $request, Procedure $procedure, Ticket $ticket)
    {
        $this->authorize('act', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($procedure->company_id) || in_array($procedure->company_id, $activeCompanyIds), 403);
        abort_unless($ticket->procedure_id === $procedure->id, 403);

        DB::transaction(function () use ($request, $ticket) {
            foreach ($request->input('inputs', []) as $recordInputId => $raw) {
                $recordInput = WorkflowRecordInput::find((int) $recordInputId);
                if (!$recordInput || $recordInput->record_id !== $ticket->id || $recordInput->record_type !== 'ticket') continue;

                if ($recordInput->type === 'select') {
                    $selectId = null;
                    if ($raw !== null && $raw !== '') {
                        $optionId = (int) $raw;
                        $valid = WorkflowTemplateInputOption::where('id', $optionId)
                            ->where('template_input_id', $recordInput->template_input_id)
                            ->exists();
                        $selectId = $valid ? $optionId : null;
                    }
                    $recordInput->update(['value_select_id' => $selectId]);
                    continue;
                }

                if ($recordInput->type === 'multiselect') {
                    // Selections persist via the workflow_record_input_multiselect
                    // pivot — value_char is unused. Validate each posted option
                    // belongs to the input's template before syncing so a malicious
                    // POST can't attach options from a different input.
                    $optionIds = collect((array) $raw)
                        ->map(fn ($v) => (int) $v)
                        ->filter(fn ($v) => $v > 0)
                        ->unique()
                        ->values();

                    $valid = WorkflowTemplateInputOption::where('template_input_id', $recordInput->template_input_id)
                        ->whereIn('id', $optionIds)
                        ->pluck('id');

                    $recordInput->selectedOptions()->sync($valid->all());
                    continue;
                }

                // 'file' and 'label' don't accept values through this endpoint
                // (files have a dedicated upload route, labels are read-only).
                if (in_array($recordInput->type, ['file', 'label'], true)) {
                    continue;
                }

                $valueData = match ($recordInput->type) {
                    'int'      => ['value_int'      => $raw !== null && $raw !== '' ? (int) $raw : null],
                    'float'    => ['value_float'    => $raw !== null && $raw !== '' ? (float) $raw : null],
                    'date'     => ['value_date'     => $raw ?: null],
                    'datetime' => ['value_datetime' => $raw ?: null],
                    'boolean'  => ['value_boolean'  => (bool) $raw],
                    'textarea' => ['value_text'     => $raw ?: null],
                    default    => ['value_char'     => $raw ?: null], // char
                };

                $recordInput->update($valueData);
            }
        });

        return back()->with('success', 'Fields saved.');
    }

    public function choosePath(Request $request, Procedure $procedure, Ticket $ticket)
    {
        $this->authorize('update', $procedure);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($procedure->company_id) || in_array($procedure->company_id, $activeCompanyIds), 403);
        $request->validate(['path_id' => 'required|exists:workflow_ticket_paths,id']);
        abort_unless($ticket->procedure_id === $procedure->id, 403);

        $path = TicketPath::findOrFail($request->path_id);
        abort_unless($path->ticket_id === $ticket->id, 422);
        DB::transaction(fn () => $this->procedureService->choosePath($ticket, $path));

        return back()->with('success', 'Path chosen.');
    }
}
