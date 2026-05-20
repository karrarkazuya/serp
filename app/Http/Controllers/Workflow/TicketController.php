<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\StoreTicketRequest;
use App\Http\Requests\Workflow\UpdateTicketRequest;
use App\Models\Chat\ChatMessageFile;
use App\Models\Chat\ChatRoom;
use App\Models\User;
use App\Models\Workflow\Department;
use App\Models\Workflow\Ticket;
use App\Models\Workflow\TicketTemplate;
use App\Models\Workflow\WorkflowTemplateInput;
use App\Models\Workflow\TicketProcedureLine;
use App\Models\Workflow\WorkflowUser;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Services\Company\CompanyContextService;
use App\Models\Workflow\WorkflowRecordInput;
use App\Services\Workflow\ProcedureService;
use App\Services\Workflow\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TicketController extends Controller
{
    private const INPUT_ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        'application/csv',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
    ];

    private const INPUT_ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf',
        'doc', 'docx',
        'xls', 'xlsx',
        'ppt', 'pptx',
        'txt', 'csv',
        'odt', 'ods',
    ];

    private const INPUT_MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

    public function __construct(
        private readonly TicketService $ticketService,
        private readonly ProcedureService $procedureService,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Ticket::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = Ticket::query()
            ->with(['template', 'assignedDepartment', 'assignedUser'])
            ->where(fn ($q) => $q->whereNull('procedure_id')->orWhere('state', '!=', 'draft'))
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

        SortsTable::apply($query, $request);

        $tickets = $query->paginate(24)->withQueryString();

        return view('workflow.tickets.index', compact('tickets'));
    }

    public function show(Ticket $ticket)
    {
        $this->authorize('view', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);

        $ticket->load([
            'template.inputs.options',
            'procedureStep.inputs.options',
            'procedure',
            'procedureLines.procedure',
            'inputs.templateInput',
            'inputs.selectedOptions',
            'assignedDepartment',
            'assignedUser',
            'sharedLink',
            'viewers',
            'createdByUser',
            'chatRoom',
        ]);

        // Lazily create a chat room for tickets created before this feature
        if (!$ticket->chatRoom) {
            $room = ChatRoom::create([
                'name'               => $ticket->name,
                'description'        => 'Ticket #' . $ticket->id,
                'created_by_user_id' => $ticket->created_by_user_id,
            ]);
            $ticket->update(['chat_room_id' => $room->id]);
            $ticket->setRelation('chatRoom', $room);
        }

        $chatMessages = $ticket->chatRoom
            ->messages()->with(['user', 'files'])
            ->orderByDesc('created_at')->limit(100)->get()
            ->reverse()->values();
        $chatGrouped  = $this->groupChatMessages($chatMessages);

        $departments = Department::where('active', true)->orderBy('name')->get();

        // Build ordered chain of previous tickets for the "Return to" selector
        $previousChain = collect();
        if ($ticket->procedure_id && $ticket->previous_ticket_id) {
            $seen = [];
            $cursor = $ticket->previousTicket;
            $iterations = 0;
            while ($cursor && $iterations < 100) {
                $iterations++;
                if (isset($seen[$cursor->id])) {
                    $previousChain = collect(); // cycle — discard chain
                    break;
                }
                $seen[$cursor->id] = true;
                $previousChain->push($cursor);
                $cursor = $cursor->previous_ticket_id ? $cursor->previousTicket : null;
            }
        }

        return view('workflow.tickets.show', compact(
            'ticket', 'chatGrouped', 'departments', 'previousChain'
        ));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Ticket::class);

        $wu = WorkflowUser::where('user_id', auth()->id())->where('active', true)->first();
        $templates = TicketTemplate::where('enabled', true)->where('active', true)
            ->when($wu, fn ($q) => $q->visibleTo($wu))
            ->orderBy('name')->get();
        $selectedTemplate = $request->query('template_id')
            ? TicketTemplate::with('inputs.options')->find($request->query('template_id'))
            : null;

        return view('workflow.tickets.create', compact('templates', 'selectedTemplate'));
    }

    public function store(StoreTicketRequest $request)
    {
        $data = $request->validated();

        $template = TicketTemplate::where('id', $data['ticket_template_id'])
            ->where('enabled', true)
            ->where('active', true)
            ->firstOrFail();

        $wu = WorkflowUser::where('user_id', auth()->id())->where('active', true)->first();
        if ($wu && !TicketTemplate::where('id', $template->id)->visibleTo($wu)->exists()) {
            abort(403, 'You do not have access to this template.');
        }

        unset($data['ticket_template_id']);

        $ticket = DB::transaction(fn () => $this->ticketService->create($data, $template));

        return redirect()->route('workflow.tickets.show', $ticket)->with('success', 'Ticket created.');
    }

    public function edit(Ticket $ticket)
    {
        abort_if($ticket->procedure_id, 403, 'Edit procedure tickets from their show page.');
        $this->authorize('update', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);

        $ticket->load(['template.inputs.options', 'inputs.selectedOptions', 'viewers']);
        $departments = Department::where('active', true)->orderBy('name')->get();

        return view('workflow.tickets.edit', compact('ticket', 'departments'));
    }

    public function write(UpdateTicketRequest $request, Ticket $ticket)
    {
        abort_if($ticket->procedure_id, 403, 'Edit procedure tickets from their show page.');
        $this->authorize('act', $ticket);

        $data = $request->validated();
        $inputs = $data['inputs'] ?? [];
        unset($data['inputs']);

        DB::transaction(function () use ($ticket, $data, $inputs) {
            $this->ticketService->update($ticket, $data);
            foreach ($inputs as $input) {
                $this->saveInput($ticket, $input);
            }
        });

        return redirect()->route('workflow.tickets.show', $ticket)->with('success', 'Ticket updated.');
    }

    // Inline field save from show page
    public function saveField(Request $request, Ticket $ticket)
    {
        $this->authorize('act', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);

        $data = $request->validate([
            'field' => 'required|in:name,description,priority,assigned_to_department_id,assigned_to_user_id',
            'value' => 'nullable',
        ]);

        $field = $data['field'];
        $value = $data['value'] ?? null;

        $allowed = ['name', 'description', 'priority', 'assigned_to_department_id', 'assigned_to_user_id'];
        abort_unless(in_array($field, $allowed), 422);

        // Enforce assignee must be an allowed user on this ticket
        if ($field === 'assigned_to_user_id' && $value) {
            $allowed = DB::table('workflow_allowed_users')
                ->where('user_id', $value)
                ->where('record_id', $ticket->id)
                ->where('record_type', 'ticket')
                ->exists();
            abort_unless($allowed, 422, 'User does not have access to this ticket.');
        }

        DB::transaction(fn () => $this->ticketService->update($ticket, [$field => $value ?: null]));

        return back()->with('success', 'Saved.');
    }

    // Inline inputs save from show page
    public function saveInputs(Request $request, Ticket $ticket)
    {
        $this->authorize('act', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);

        $ownerId   = $ticket->procedure_step_id ?? $ticket->template_id;
        $ownerType = $ticket->procedure_step_id ? 'procedure_step' : 'ticket_template';
        abort_unless($ownerId, 422);

        // Load all template inputs for this ticket's current step/template
        $templateInputs = WorkflowTemplateInput::where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->with('options')
            ->get()
            ->keyBy('id');

        $rawInputs  = $request->input('inputs', []);
        $fileInputs = $request->file('inputs', []);

        // Pass 1: validate all files before storing any — prevents orphaned files if a
        // later validation fails and aborts before the try/catch cleanup runs.
        $fileInputs = $fileInputs ?? [];
        foreach ($templateInputs->where('type', 'file') as $tid => $templateInput) {
            $file = $fileInputs[$tid] ?? null;
            if (!$file || !$file->isValid()) continue;

            abort_if(
                $file->getSize() > self::INPUT_MAX_FILE_SIZE,
                422,
                "File too large for field \"{$templateInput->name}\" (max 10 MB)."
            );

            // MIME checked via finfo — reads actual file bytes, not user-supplied header
            abort_unless(
                in_array($file->getMimeType(), self::INPUT_ALLOWED_MIMES),
                422,
                "File type not allowed for field \"{$templateInput->name}\"."
            );

            // Extension check as defence-in-depth (client-supplied, secondary gate)
            abort_unless(
                in_array(strtolower($file->getClientOriginalExtension()), self::INPUT_ALLOWED_EXTENSIONS),
                422,
                "File extension not allowed for field \"{$templateInput->name}\"."
            );
        }

        // Pass 2: all validations passed — now store files
        $storedFiles = [];
        foreach ($templateInputs->where('type', 'file') as $tid => $templateInput) {
            $file = $fileInputs[$tid] ?? null;
            if (!$file || !$file->isValid()) continue;

            $path = $file->store("workflow/inputs/{$ticket->id}", 'local');
            $storedFiles[$tid] = [
                'value_file_path' => $path,
                'value_file_name' => basename($file->getClientOriginalName()),
                'value_file_mime' => $file->getMimeType(),
                'value_file_size' => $file->getSize(),
            ];
        }

        try {
            DB::transaction(function () use ($rawInputs, $storedFiles, $templateInputs, $ticket) {
                foreach ($templateInputs as $tid => $templateInput) {
                    if ($templateInput->type === 'file') {
                        if (!isset($storedFiles[$tid])) continue;
                        $this->ticketService->saveInputValue($ticket, $tid, $storedFiles[$tid]);

                    } elseif ($templateInput->type === 'multiselect') {
                        $raw = $rawInputs[$tid] ?? [];
                        $raw = is_array($raw) ? $raw : [];
                        $validOptionIds = $templateInput->options->pluck('id')->toArray();
                        $selectedIds    = array_values(array_intersect(array_map('intval', $raw), $validOptionIds));
                        $this->ticketService->saveInputValue($ticket, $tid, ['_selected_option_ids' => $selectedIds]);

                    } elseif (array_key_exists((string) $tid, $rawInputs)) {
                        $raw       = $rawInputs[$tid];
                        $valueData = match ($templateInput->type) {
                            'int'      => ['value_int'       => $raw !== null && $raw !== '' ? (int) $raw : null],
                            'float'    => ['value_float'     => $raw !== null && $raw !== '' ? (float) $raw : null],
                            'date'     => ['value_date'      => $raw ?: null],
                            'datetime' => ['value_datetime'  => $raw ?: null],
                            'boolean'  => ['value_boolean'   => (bool) $raw],
                            'select'   => ['value_select_id' => $this->validatedSelectOption($templateInput, $raw)],
                            'textarea' => ['value_text'      => $raw ?: null],
                            default    => ['value_char'      => $raw ?: null],
                        };
                        $this->ticketService->saveInputValue($ticket, $tid, $valueData);
                    }
                }
            });
        } catch (\Throwable $e) {
            // Clean up any files we stored before the transaction failed
            foreach ($storedFiles as $fileData) {
                Storage::disk('local')->delete($fileData['value_file_path']);
            }
            throw $e;
        }

        return back()->with('success', 'Fields saved.');
    }

    public function downloadInputFile(Ticket $ticket, WorkflowRecordInput $recordInput)
    {
        $this->authorize('view', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);

        // Ensure the file record actually belongs to this ticket
        abort_unless(
            (int) $recordInput->record_id === $ticket->id && $recordInput->record_type === 'ticket',
            403
        );
        abort_unless($recordInput->type === 'file' && $recordInput->value_file_path, 404);
        abort_unless(Storage::disk('local')->exists($recordInput->value_file_path), 404);

        return Storage::disk('local')->download(
            $recordInput->value_file_path,
            $recordInput->value_file_name,
            ['Content-Type' => $recordInput->value_file_mime ?? 'application/octet-stream']
        );
    }

    public function deleteInputFile(Ticket $ticket, WorkflowRecordInput $recordInput)
    {
        $this->authorize('act', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);

        abort_unless(
            (int) $recordInput->record_id === $ticket->id && $recordInput->record_type === 'ticket',
            403
        );
        abort_unless($recordInput->type === 'file', 422);

        DB::transaction(function () use ($recordInput) {
            if ($recordInput->value_file_path) {
                Storage::disk('local')->delete($recordInput->value_file_path);
            }
            $recordInput->update([
                'value_file_path' => null,
                'value_file_name' => null,
                'value_file_mime' => null,
                'value_file_size' => null,
            ]);
        });

        return back()->with('success', 'File removed.');
    }

    // Viewer management from show page
    public function addViewer(Request $request, Ticket $ticket)
    {
        $this->authorize('update', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);

        $request->validate(['user_id' => 'required|exists:users,id']);
        $user = User::findOrFail($request->user_id);

        DB::transaction(fn () => $this->ticketService->addViewer($ticket, $user));

        return back()->with('success', "{$user->name} added as viewer.");
    }

    public function removeViewer(Ticket $ticket, User $user)
    {
        $this->authorize('update', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->ticketService->removeViewer($ticket, $user));

        return back()->with('success', 'Viewer removed.');
    }

    public function resolve(Ticket $ticket)
    {
        $this->authorize('act', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);

        if ($ticket->has_path_choice && $ticket->path_choice_required && !$ticket->path_chosen_id) {
            return back()->with('error', 'You must select a path before completing this ticket.');
        }

        if ($ticket->has_procedures && $ticket->procedures_required && !$ticket->hasAllProcedureLinesCompleted()) {
            return back()->with('error', 'All sub-procedures must be completed before this ticket can be completed.');
        }

        if (!$ticket->hasRequiredInputsFilled()) {
            return back()->with('error', 'All required fields must be filled before completing this ticket.');
        }

        DB::transaction(fn () => $this->ticketService->resolve($ticket));

        return back()->with('success', 'Ticket resolved.');
    }

    public function close(Request $request, Ticket $ticket)
    {
        $this->authorize('act', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);

        $reason = null;
        $returnToTicketId = null;

        if ($ticket->procedure_id && $ticket->previous_ticket_id) {
            $request->validate(['return_reason' => 'required|string|max:1000']);
            $reason = $request->input('return_reason');

            if ($request->filled('return_to_ticket_id')) {
                $returnToTicketId = (int) $request->input('return_to_ticket_id');

                // Validate target is actually a predecessor (cycle-safe walk)
                $seen = [];
                $cursor = $ticket->previousTicket;
                $found = false;
                $iterations = 0;
                while ($cursor && $iterations < 100) {
                    $iterations++;
                    if (isset($seen[$cursor->id])) break;
                    $seen[$cursor->id] = true;
                    if ($cursor->id === $returnToTicketId) { $found = true; break; }
                    $cursor = $cursor->previous_ticket_id ? $cursor->previousTicket : null;
                }

                if (!$found) {
                    return back()->withErrors(['return_to_ticket_id' => 'Invalid return target.']);
                }
            }
        }

        DB::transaction(fn () => $this->ticketService->close($ticket, $reason, $returnToTicketId));

        return back()->with('success', 'Ticket returned.');
    }

    public function reopen(Ticket $ticket)
    {
        $this->authorize('update', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $this->ticketService->reopen($ticket));

        return back()->with('success', 'Ticket reopened.');
    }

    public function archive(Ticket $ticket)
    {
        $this->authorize('update', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);
        abort_if($ticket->state === 'pending', 403, 'Cannot archive an open ticket.');
        DB::transaction(fn () => $this->ticketService->archive($ticket));

        return redirect()->route('workflow.tickets.index')->with('success', 'Ticket archived.');
    }

    public function unarchive(Ticket $ticket)
    {
        $this->authorize('update', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $this->ticketService->unarchive($ticket));

        return back()->with('success', 'Ticket restored.');
    }

    public function unlink(Ticket $ticket)
    {
        $this->authorize('delete', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $this->ticketService->delete($ticket));

        return redirect()->route('workflow.tickets.index')->with('success', 'Ticket deleted.');
    }

    public function viewersLookup(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);

        $search  = $request->query('search', '');
        $perPage = max(1, min((int) $request->integer('per_page', 8), 50));

        $query = User::whereIn('id', function ($sub) use ($ticket) {
                $sub->select('user_id')
                    ->from('workflow_allowed_users')
                    ->where('record_id', $ticket->id)
                    ->where('record_type', 'ticket');
            })
            ->where('active', true)
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"));

        $paginator = $query->orderBy('name')->paginate($perPage);

        $paginator->getCollection()->transform(fn ($u) => [
            'id'    => $u->id,
            'label' => $u->name,
            'color' => null,
        ]);

        return response()->json($paginator);
    }

    public function addComment(Request $request, Ticket $ticket)
    {
        $this->authorize('comment', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);
        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $ticket->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }

    public function startSubProcedure(Request $request, Ticket $ticket, TicketProcedureLine $line)
    {
        $this->authorize('act', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);
        abort_unless($line->ticket_id === $ticket->id, 403);

        // Allow start only when there is no procedure yet, or the existing one was cancelled
        $existing = $line->procedure;
        abort_if($existing && $existing->state !== 'closed', 422, 'Sub-procedure is already running or completed.');

        $template = \App\Models\Workflow\ProcedureTemplate::findOrFail($line->procedure_template_id);

        $procedure = DB::transaction(function () use ($ticket, $line, $template) {
            $procedure = $this->procedureService->create([
                'name'                 => $line->name,
                'optional_ticket_id'   => $ticket->id,
                'optional_procedure_id' => $ticket->procedure_id,
            ], $template);

            $line->update(['procedure_id' => $procedure->id]);

            return $procedure;
        });

        return redirect()->route('workflow.procedures.show', $procedure)
            ->with('success', 'Sub-procedure started.');
    }

    private function saveInput(Ticket $ticket, array $input): void
    {
        $templateInput = WorkflowTemplateInput::find($input['template_input_id']);
        if (!$templateInput) return;

        // Reject inputs that don't belong to this ticket's own template
        if ($templateInput->owner_type !== 'ticket_template' || $templateInput->owner_id !== $ticket->template_id) {
            return;
        }

        $type = $templateInput->type;
        $raw  = $input['value'] ?? null;

        // File uploads cannot be processed via the edit form (value is a string, not a file stream)
        if ($type === 'file') return;

        // Multiselect is also handled only on the show page fields form
        if ($type === 'multiselect') return;

        $valueData = match ($type) {
            'char'     => ['value_char'      => $raw],
            'int'      => ['value_int'       => $raw !== null && $raw !== '' ? (int) $raw : null],
            'float'    => ['value_float'     => $raw !== null && $raw !== '' ? (float) $raw : null],
            'date'     => ['value_date'      => $raw ?: null],
            'datetime' => ['value_datetime'  => $raw ?: null],
            'boolean'  => ['value_boolean'   => (bool) $raw],
            'select'   => ['value_select_id' => $this->validatedSelectOption($templateInput, $raw)],
            'textarea' => ['value_text'      => $raw ?: null],
            default    => ['value_char'      => $raw],
        };

        $this->ticketService->saveInputValue($ticket, $templateInput->id, $valueData);
    }

    private function validatedSelectOption(\App\Models\Workflow\WorkflowTemplateInput $templateInput, mixed $raw): ?int
    {
        if ($raw === null || $raw === '') return null;
        $id = (int) $raw;
        // Ensure the submitted option actually belongs to this template input
        return $templateInput->options->pluck('id')->contains($id) ? $id : null;
    }

    private const CHAT_ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain', 'text/csv',
    ];

    public function sendChat(Request $request, Ticket $ticket)
    {
        $this->authorize('update', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);
        abort_unless($ticket->chatRoom, 404);

        $request->validate([
            'body'    => 'nullable|string|max:5000',
            'files.*' => ['nullable', 'file', 'max:10240'],
        ]);

        $body  = trim($request->input('body', ''));
        $files = $request->file('files', []);
        abort_if(empty($body) && empty(array_filter($files)), 422);

        $message = $ticket->chatRoom->messages()->create([
            'user_id' => \Illuminate\Support\Facades\Auth::id(),
            'body'    => $body ?: null,
        ]);

        foreach ($files as $file) {
            if (!$file || !in_array($file->getMimeType(), self::CHAT_ALLOWED_MIMES)) continue;
            $path = $file->store("chat/{$ticket->chatRoom->id}", 'local');
            ChatMessageFile::create([
                'message_id'    => $message->id,
                'disk'          => 'local',
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getMimeType(),
                'size'          => $file->getSize(),
            ]);
        }

        return back();
    }

    public function chatFile(Ticket $ticket, ChatMessageFile $file)
    {
        $this->authorize('view', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);
        abort_unless($ticket->chatRoom && $file->message->room_id === $ticket->chatRoom->id, 403);

        $fullPath = \Illuminate\Support\Facades\Storage::disk($file->disk)->path($file->path);
        return response()->download($fullPath, $file->original_name);
    }

    private function groupChatMessages(\Illuminate\Support\Collection $messages): array
    {
        $grouped    = [];
        $prevUserId = null;
        $prevDate   = null;

        foreach ($messages as $msg) {
            $date       = $msg->created_at->format('Y-m-d');
            $showDate   = $date !== $prevDate;
            $showHeader = $msg->user_id !== $prevUserId || $showDate;

            $label = match (true) {
                $msg->created_at->isToday()     => 'Today',
                $msg->created_at->isYesterday() => 'Yesterday',
                default                         => $msg->created_at->format('F j, Y'),
            };

            $grouped[]  = [
                'message'     => $msg,
                'show_date'   => $showDate,
                'show_header' => $showHeader,
                'date_label'  => $label,
            ];

            $prevUserId = $msg->user_id;
            $prevDate   = $date;
        }

        return $grouped;
    }
}
