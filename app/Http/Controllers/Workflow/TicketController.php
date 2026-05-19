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
use App\Models\Workflow\WorkflowUser;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Services\Company\CompanyContextService;
use App\Services\Workflow\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
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
            'inputs.templateInput',
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

        $messages    = $ticket->chatterMessages()->with('user')->latest()->get();
        $departments = Department::where('active', true)->orderBy('name')->get();

        return view('workflow.tickets.show', compact(
            'ticket', 'messages', 'chatGrouped', 'departments'
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
        $template = TicketTemplate::findOrFail($data['ticket_template_id']);
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

        $ticket->load(['template.inputs.options', 'inputs', 'viewers']);
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

        $rawInputs = $request->input('inputs', []);

        if (!empty($rawInputs)) {
            if ($ticket->procedure_step_id) {
                $templateInputs = WorkflowTemplateInput::whereIn('id', array_keys($rawInputs))
                    ->where('owner_type', 'procedure_step')
                    ->where('owner_id', $ticket->procedure_step_id)
                    ->get()
                    ->keyBy('id');
            } else {
                $templateInputs = WorkflowTemplateInput::whereIn('id', array_keys($rawInputs))
                    ->where('owner_type', 'ticket_template')
                    ->where('owner_id', $ticket->template_id)
                    ->get()
                    ->keyBy('id');
            }

            DB::transaction(function () use ($rawInputs, $templateInputs, $ticket) {
                foreach ($rawInputs as $templateInputId => $raw) {
                    $templateInput = $templateInputs->get((int) $templateInputId);
                    if (!$templateInput) continue;

                    $valueData = match ($templateInput->type) {
                        'int'      => ['value_int'      => $raw !== null && $raw !== '' ? (int) $raw : null],
                        'date'     => ['value_date'     => $raw ?: null],
                        'datetime' => ['value_datetime' => $raw ?: null],
                        'boolean'  => ['value_boolean'  => (bool) $raw],
                        'select'   => ['value_select_id' => $raw !== null && $raw !== '' ? (int) $raw : null],
                        default    => ['value_char'     => $raw ?: null],
                    };

                    $this->ticketService->saveInputValue($ticket, $templateInput->id, $valueData);
                }
            });
        }

        return back()->with('success', 'Fields saved.');
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
        DB::transaction(fn () => $this->ticketService->resolve($ticket));

        return back()->with('success', 'Ticket resolved.');
    }

    public function close(Ticket $ticket)
    {
        $this->authorize('act', $ticket);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($ticket->company_id) || in_array($ticket->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $this->ticketService->close($ticket));

        return back()->with('success', 'Ticket closed.');
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

    private function saveInput(Ticket $ticket, array $input): void
    {
        $templateInput = WorkflowTemplateInput::find($input['template_input_id']);
        if (!$templateInput) return;

        $type = $templateInput->type;
        $raw  = $input['value'] ?? null;

        $valueData = match ($type) {
            'char'     => ['value_char'     => $raw],
            'int'      => ['value_int'      => $raw !== null ? (int) $raw : null],
            'date'     => ['value_date'     => $raw],
            'datetime' => ['value_datetime' => $raw],
            'boolean'  => ['value_boolean'  => (bool) $raw],
            'select'   => ['value_select_id' => $raw !== null ? (int) $raw : null],
            default    => ['value_char'     => $raw],
        };

        $this->ticketService->saveInputValue($ticket, $templateInput->id, $valueData);
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
