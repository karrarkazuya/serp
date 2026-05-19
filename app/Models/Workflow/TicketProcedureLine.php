<?php

namespace App\Models\Workflow;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketProcedureLine extends Model
{
    protected $table = 'workflow_ticket_procedure_lines';

    protected $fillable = [
        'uuid', 'ticket_id', 'procedure_template_id', 'name', 'state',
        'active', 'created_by', 'updated_by',
    ];

    protected $casts = ['active' => 'boolean'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function procedureTemplate(): BelongsTo
    {
        return $this->belongsTo(ProcedureTemplate::class, 'procedure_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
