<?php

namespace App\Models\Workflow;

use App\Models\Contacts\Contact;
use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Procedure extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'workflow_procedures';

    public const STATES = ['pending', 'completed', 'closed'];

    public array $sortable = [
        'id'          => 'id',
        'name'        => 'name',
        'state'       => 'state',
        'created_at'  => 'created_at',
    ];

    public array $searchable = [
        'name'       => ['label' => 'Name', 'column' => 'name', 'type' => 'string'],
        'state'      => ['label' => 'State', 'column' => 'state', 'options' => self::STATES],
        'active'     => ['label' => 'Active', 'column' => 'active', 'type' => 'boolean'],
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    public array $chatterTracked = [
        'name'  => 'Name',
        'state' => 'State',
    ];

    protected $fillable = [
        'uuid', 'procedure_template_id', 'company_id', 'name', 'description', 'state',
        'created_by_user_id',
        'optional_contact_id', 'optional_ticket_id', 'optional_procedure_id',
        'active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function procedureTemplate(): BelongsTo
    {
        return $this->belongsTo(ProcedureTemplate::class, 'procedure_template_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function optionalContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'optional_contact_id');
    }

    public function optionalTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'optional_ticket_id');
    }

    public function parentProcedure(): BelongsTo
    {
        return $this->belongsTo(self::class, 'optional_procedure_id');
    }

    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workflow_procedure_viewers', 'procedure_id', 'user_id');
    }

    public function sharedLink(): MorphOne
    {
        return $this->morphOne(WorkflowSharedLink::class, 'shareable');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'procedure_id')->orderBy('id');
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
                ->from('workflow_procedure_viewers')
                ->whereColumn('procedure_id', 'workflow_procedures.id')
                ->where('user_id', $user->id)
                ->whereExists(fn ($wu) => $wu->selectRaw('1')->from('workflow_users')
                    ->where('user_id', $user->id)->where('active', true));
        });
    }

    public function stateLabel(): string
    {
        return match ($this->state) {
            'completed' => 'Completed',
            'closed'    => 'Canceled',
            default     => 'In Progress',
        };
    }

    public function stateColor(): string
    {
        return match ($this->state) {
            'completed' => 'bg-green-100 text-green-700',
            'closed'    => 'bg-gray-100 text-gray-500',
            default     => 'bg-blue-100 text-blue-700',
        };
    }

    public function pendingTickets(): HasMany
    {
        return $this->tickets()->where('state', 'pending');
    }

    public function hasPendingTickets(): bool
    {
        return $this->tickets()
            ->where('state', 'pending')
            ->where('ignore_state', false)
            ->exists();
    }
}
