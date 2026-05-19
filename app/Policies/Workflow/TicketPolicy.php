<?php

namespace App\Policies\Workflow;

use App\Models\User;
use App\Models\Workflow\Ticket;
use Illuminate\Support\Facades\DB;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('workflow.tickets.read');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if (!$user->hasPermission('workflow.tickets.read')) return false;
        // Draft procedure tickets are invisible to everyone except admins
        if ($ticket->procedure_id && $ticket->state === 'draft') {
            return $user->hasPermission('workflow.admin');
        }
        if ($user->hasPermission('workflow.admin')) return true;
        return $this->isAllowed($user, $ticket);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('workflow.tickets.create');
    }

    /**
     * General write access: archive, viewer management, metadata edits.
     * Does NOT require the ticket to be pending.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        if (!$user->hasPermission('workflow.tickets.write')) return false;
        if ($user->hasPermission('workflow.admin')) return true;
        return $this->isAllowed($user, $ticket);
    }

    /**
     * Act = any mutation that drives ticket state or fields forward.
     * Requires the ticket to be pending and the user to be an allowed viewer.
     */
    public function act(User $user, Ticket $ticket): bool
    {
        if ($ticket->state !== 'pending') return false;
        if (!$user->hasPermission('workflow.tickets.write')) return false;
        if ($user->hasPermission('workflow.admin')) return true;
        return $this->isAllowed($user, $ticket);
    }

    public function delete(): bool
    {
        return false;
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        if (!$user->hasPermission('workflow.tickets.write')) return false;
        if ($user->hasPermission('workflow.admin')) return true;
        return $this->isAllowed($user, $ticket);
    }

    private function isAllowed(User $user, Ticket $ticket): bool
    {
        return DB::table('workflow_allowed_users')
            ->where('user_id', $user->id)
            ->where('record_id', $ticket->id)
            ->where('record_type', 'ticket')
            ->exists();
    }
}
