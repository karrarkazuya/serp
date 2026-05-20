<?php

namespace App\Policies\Workflow;

use App\Models\User;
use App\Models\Workflow\Ticket;
use Illuminate\Auth\Access\Response;
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
        if ($user->hasPermission('workflow.admin')) return true;
        // Draft procedure tickets have no viewers seeded yet — deny non-admins
        if ($ticket->procedure_id && $ticket->state === 'draft') return false;
        return $this->isAllowed($user, $ticket);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('workflow.tickets.create');
    }

    /**
     * General write access: viewer management, metadata edits.
     * Blocked when the procedure is closed/completed or when a next ticket is already active.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        if (!$user->hasPermission('workflow.tickets.write')) return false;

        if ($ticket->procedure_id) {
            if ($ticket->procedure->state !== 'pending') return false;
            if ($this->nextTicketIsActive($ticket)) return false;
        }

        if ($user->hasPermission('workflow.admin')) return true;
        return $this->isAllowed($user, $ticket);
    }

    /**
     * Act = any mutation that drives ticket state or fields forward.
     * Requires: ticket pending + write permission + viewer.
     * Also blocked when procedure is closed or a next ticket is already active.
     */
    public function act(User $user, Ticket $ticket): Response|bool
    {
        if ($ticket->state !== 'pending') return false;

        if ($ticket->procedure_id && $ticket->procedure->state !== 'pending') {
            return Response::deny('This procedure is no longer active.');
        }

        if (!$user->hasPermission('workflow.tickets.write')) return false;
        if ($user->hasPermission('workflow.admin')) return true;
        return $this->isAllowed($user, $ticket);
    }

    public function delete(): bool
    {
        return false;
    }

    /**
     * Chat comments are also locked when the procedure is no longer active
     * or when a next ticket has already started.
     */
    public function comment(User $user, Ticket $ticket): Response|bool
    {
        if (!$user->hasPermission('workflow.tickets.write')) return false;

        if ($ticket->procedure_id) {
            if ($ticket->procedure->state !== 'pending') {
                return Response::deny('This procedure is no longer active — comments are locked.');
            }
            if ($this->nextTicketIsActive($ticket)) {
                return Response::deny('Cannot comment: a subsequent ticket is already in progress.');
            }
        }

        if ($user->hasPermission('workflow.admin')) return true;
        return $this->isAllowed($user, $ticket);
    }

    private function nextTicketIsActive(Ticket $ticket): bool
    {
        return $ticket->nextTickets()->whereIn('state', ['pending', 'completed'])->exists();
    }

    private function isAllowed(User $user, Ticket $ticket): bool
    {
        return DB::table('workflow_allowed_users')
            ->where('user_id', $user->id)
            ->where('record_id', $ticket->id)
            ->where('record_type', 'ticket')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('workflow_users')
                ->where('user_id', $user->id)->where('active', true))
            ->exists();
    }
}
