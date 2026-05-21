<?php

namespace App\Models\Workflow;

use App\Models\Contacts\Contact;
use App\Models\Employees\Department;
use App\Models\Settings\Company;
use App\Models\User;
use App\Models\Chat\ChatRoom;
use App\Traits\HasChatter;
use App\Models\Workflow\WorkflowTemplateInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Ticket extends Model
{
    use HasChatter;

    protected $table = 'workflow_tickets';

    public const STATES = ['draft', 'pending', 'completed', 'rejected', 'skipped', 'closed'];
    public const PRIORITIES = ['1', '2', '3'];

    public array $sortable = [
        'id'          => 'id',
        'name'        => 'name',
        'state'       => 'state',
        'priority'    => 'priority',
        'deadline'    => 'resolve_deadline',
        'duration'    => 'resolve_duration',
        'sla_passed'  => 'resolve_deadline_passed',
        'sla_limit'   => 'resolve_max_duration',
        'created_at'  => 'created_at',
    ];

    public array $searchable = [
        'name'       => ['label' => 'Name', 'column' => 'name', 'type' => 'string'],
        'state'      => ['label' => 'State', 'column' => 'state', 'type' => 'string'],
        'priority'   => ['label' => 'Priority', 'column' => 'priority', 'type' => 'string'],
        'active'     => ['label' => 'Active', 'column' => 'active', 'type' => 'boolean'],
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    public array $chatterTracked = [
        'name'                      => 'Name',
        'state'                     => 'State',
        'priority'                  => 'Priority',
        'assigned_to_department_id' => ['label' => 'Department',    'table' => 'hr_departments'],
        'assigned_to_user_id'       => ['label' => 'Assigned User', 'table' => 'users'],
        'return_reason'             => 'Return Reason',
    ];

    protected $fillable = [
        'uuid', 'template_id', 'procedure_id', 'procedure_step_id',
        'company_id', 'name', 'description', 'state', 'priority',
        'is_approve_only', 'has_path_choice', 'path_choice_question', 'path_choice_required',
        'has_procedures', 'procedures_required', 'ignore_state', 'return_reason',
        'assigned_to_department_id', 'assigned_to_user_id', 'created_by_user_id',
        'previous_ticket_id', 'path_chosen_id',
        'resolve_max_duration', 'resolve_deadline', 'resolve_duration', 'resolve_deadline_passed',
        'unlock_at', 'finished_creation',
        'share_enabled', 'share_token', 'optional_contact_id', 'optional_ticket_id',
        'active', 'created_by', 'updated_by', 'chat_room_id',
    ];

    protected $casts = [
        'is_approve_only'   => 'boolean',
        'has_path_choice'        => 'boolean',
        'path_choice_required'   => 'boolean',
        'has_procedures'         => 'boolean',
        'procedures_required'    => 'boolean',
        'has_procedures'    => 'boolean',
        'ignore_state'      => 'boolean',
        'finished_creation' => 'boolean',
        'share_enabled'     => 'boolean',
        'active'            => 'boolean',
        'unlock_at'         => 'datetime',
        'resolve_deadline'  => 'datetime',
    ];

    public function chatRoom(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TicketTemplate::class, 'template_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'assigned_to_department_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function optionalContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'optional_contact_id');
    }

    public function parentTicket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'optional_ticket_id');
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class, 'procedure_id');
    }

    public function procedureStep(): BelongsTo
    {
        return $this->belongsTo(ProcedureStep::class, 'procedure_step_id');
    }

    public function previousTicket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_ticket_id');
    }

    public function nextTickets(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'workflow_ticket_next', 'ticket_id', 'next_ticket_id');
    }

    public function pathChoices(): HasMany
    {
        return $this->hasMany(TicketPath::class, 'ticket_id');
    }

    public function pathChosen(): BelongsTo
    {
        return $this->belongsTo(TicketPath::class, 'path_chosen_id');
    }

    public function procedureLines(): HasMany
    {
        return $this->hasMany(TicketProcedureLine::class, 'ticket_id');
    }

    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workflow_allowed_users', 'record_id', 'user_id')
            ->wherePivot('record_type', 'ticket')
            ->withPivotValue('record_type', 'ticket');
    }

    public function sharedLink(): MorphOne
    {
        return $this->morphOne(WorkflowSharedLink::class, 'shareable');
    }

    public function inputs(): HasMany
    {
        return $this->hasMany(WorkflowRecordInput::class, 'record_id')
            ->where('record_type', 'ticket');
    }

    public function durations(): HasMany
    {
        return $this->hasMany(TicketDuration::class, 'ticket_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('workflow.admin')) {
            return $query;
        }
        return $query->whereExists(function ($sub) use ($user) {
            $sub->selectRaw('1')
                ->from('workflow_allowed_users')
                ->whereColumn('record_id', 'workflow_tickets.id')
                ->where('record_type', 'ticket')
                ->where('user_id', $user->id)
                ->whereExists(fn ($wu) => $wu->selectRaw('1')->from('workflow_users')
                    ->where('user_id', $user->id)->where('active', true));
        });
    }

    public function isOverdue(): bool
    {
        return $this->resolve_deadline && now()->isAfter($this->resolve_deadline) && $this->state === 'pending';
    }

    public function priorityLabel(): string
    {
        return match ($this->priority) {
            '3' => 'High',
            '2' => 'Medium',
            default => 'Normal',
        };
    }

    public function priorityColor(): string
    {
        return match ($this->priority) {
            '3' => 'text-red-600',
            '2' => 'text-amber-600',
            default => 'text-gray-500',
        };
    }

    public function stateLabel(): string
    {
        return match ($this->state) {
            'draft'     => 'Waiting',
            'pending'   => 'Open',
            'completed' => 'Completed',
            'rejected'  => 'Returned',
            'skipped'   => 'Skipped',
            'closed'    => 'Closed',
            default     => 'Open',
        };
    }

    public function stateColor(): string
    {
        return match ($this->state) {
            'draft'     => 'bg-gray-100 text-gray-500',
            'pending'   => 'bg-blue-100 text-blue-700',
            'completed' => 'bg-green-100 text-green-700',
            'rejected'  => 'bg-red-100 text-red-700',
            'skipped'   => 'bg-gray-100 text-gray-400',
            'closed'    => 'bg-gray-100 text-gray-600',
            default     => 'bg-blue-100 text-blue-700',
        };
    }

    public function hasRequiredInputsFilled(): bool
    {
        $ownerId   = $this->procedure_step_id ?? $this->template_id;
        $ownerType = $this->procedure_step_id ? 'procedure_step' : 'ticket_template';

        if (!$ownerId) return true;

        $requiredIds = WorkflowTemplateInput::where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('is_required', true)
            ->where('type', '!=', 'label')
            ->pluck('id');

        if ($requiredIds->isEmpty()) return true;

        $filled = $this->inputs()
            ->whereIn('template_input_id', $requiredIds)
            ->with('selectedOptions')
            ->get()
            ->keyBy('template_input_id');

        foreach ($requiredIds as $tid) {
            $record = $filled->get($tid);
            if (!$record || !$record->isFilled()) {
                return false;
            }
        }

        return true;
    }

    public function hasAllProcedureLinesCompleted(): bool
    {
        if (!$this->has_procedures) return true;

        // A line blocks completion if: never started, or its procedure is not completed
        return !$this->procedureLines()
            ->where(fn ($q) => $q
                ->whereNull('procedure_id')
                ->orWhereHas('procedure', fn ($p) => $p->where('state', '!=', 'completed'))
            )
            ->exists();
    }
}
