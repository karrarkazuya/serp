<?php

namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketPath extends Model
{
    protected $table = 'workflow_ticket_paths';

    protected $fillable = ['ticket_id', 'target_ticket_id', 'name'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function targetTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'target_ticket_id');
    }
}
