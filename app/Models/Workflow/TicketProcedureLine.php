<?php

namespace App\Models\Workflow;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class TicketProcedureLine extends Model
{
    use SoftDeletes;

    protected $table = 'workflow_ticket_procedure_lines';

    protected $fillable = [
        'uuid', 'ticket_id', 'procedure_template_id', 'procedure_id', 'name', 'state',
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

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class, 'procedure_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
