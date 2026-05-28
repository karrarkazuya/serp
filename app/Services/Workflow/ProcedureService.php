<?php

namespace App\Services\Workflow;

use App\Models\User;
use App\Models\Workflow\Procedure;
use App\Models\Workflow\ProcedureTemplate;
use App\Models\Workflow\Ticket;
use App\Models\Workflow\TicketPath;
use App\Models\Workflow\WorkflowUser;
use App\Services\Chatter\ChatterService;
use Illuminate\Support\Facades\DB;

class ProcedureService
{
    public function __construct(
        private readonly ChatterService $chatterService
    ) {}

    public function create(array $data, ProcedureTemplate $template): Procedure
    {
        $actorId = auth()->user()?->id;

        $procedure = Procedure::create(array_merge($data, [
            'procedure_template_id' => $template->id,
            'state'                 => 'pending',
            'created_by_user_id'    => $actorId,
        ]));

        if ($actorId) {
            $procedure->viewers()->sync([$actorId]);
        }

        $this->instantiateTickets($procedure, $template);
        $this->chatterService->logCreated($procedure, 'Procedure');

        return $procedure;
    }

    public function completeTicket(Ticket $ticket): void
    {
        $duration = (int) round(now()->diffInHours($ticket->created_at, true));
        $passed   = max(0, $duration - ($ticket->resolve_max_duration ?? 0));

        $ticket->update([
            'state'                   => 'completed',
            'resolve_duration'        => $duration,
            'resolve_deadline_passed' => $passed,
        ]);
        $this->chatterService->log($ticket, 'Ticket marked as completed.', 'system');
        $this->chatterService->log($ticket->procedure, "Ticket '{$ticket->name}' completed.", 'system');

        if ($ticket->ignore_state) return;

        $procedure  = $ticket->procedure;
        $hasNext    = $ticket->nextTickets()->exists();
        $hasPending = $procedure->tickets()->where('id', '!=', $ticket->id)->where('state', 'pending')->where('ignore_state', false)->exists();

        if (!$hasNext && !$hasPending) {
            $this->finishProcedureAsCompleted($procedure);
        } else {
            $this->unlockNextTickets($ticket);
        }
    }

    public function rejectTicket(Ticket $ticket, ?string $reason = null, ?int $returnToTicketId = null): void
    {
        $ticket->update(['state' => 'rejected']);
        $this->chatterService->log($ticket, 'Ticket rejected.', 'system');
        $this->chatterService->log($ticket->procedure, "Ticket '{$ticket->name}' rejected.", 'system');

        if ($ticket->ignore_state) return;

        if ($ticket->previous_ticket_id) {
            $targetId = $returnToTicketId ?? $ticket->previous_ticket_id;
            $this->rejectChainToTarget($ticket, $targetId, $reason);
        } else {
            $hasPending = $ticket->procedure->tickets()
                ->where('id', '!=', $ticket->id)
                ->where('state', 'pending')
                ->where('ignore_state', false)
                ->exists();

            if (!$hasPending) {
                $ticket->procedure->tickets()->where('state', 'draft')->update(['state' => 'skipped']);
                $this->finishProcedureAsCancelled($ticket->procedure);
            }
        }
    }

    public function choosePath(Ticket $ticket, TicketPath $path): void
    {
        $ticket->update(['path_chosen_id' => $path->id]);

        // Skip all unchosen path targets so they are excluded from the next-unlock step.
        // The chosen target stays in draft and is unlocked when this ticket is completed.
        foreach ($ticket->pathChoices as $p) {
            if ($p->id === $path->id) continue;
            $target = $p->targetTicket;
            if ($target && !in_array($target->state, ['skipped', 'completed', 'rejected'])) {
                $target->update(['state' => 'skipped']);
            }
        }

        $this->chatterService->log($ticket, "Path selected: {$path->name}.", 'system');
        if ($ticket->procedure) {
            $this->chatterService->log($ticket->procedure, "Path '{$path->name}' selected for ticket '{$ticket->name}'.", 'system');
        }
    }

    public function close(Procedure $procedure): Procedure
    {
        $procedure->tickets()->whereIn('state', ['draft', 'pending'])->update(['state' => 'skipped']);
        $procedure->update(['state' => 'closed']);
        $this->chatterService->log($procedure, 'Procedure canceled.', 'system');

        if ($procedure->created_by_user_id && $procedure->created_by_user_id !== auth()->user()?->id) {
            $creator = User::find($procedure->created_by_user_id);
            $creator?->notify(
                "Procedure canceled: {$procedure->name}",
                '',
                route('workflow.procedures.show', $procedure)
            );
        }

        return $procedure;
    }

    public function archive(Procedure $procedure): Procedure
    {
        $procedure->update(['active' => false]);
        $this->chatterService->logArchived($procedure, 'Procedure');

        return $procedure;
    }

    public function unarchive(Procedure $procedure): Procedure
    {
        $procedure->update(['active' => true]);
        $this->chatterService->logUnarchived($procedure, 'Procedure');

        return $procedure;
    }

    public function delete(Procedure $procedure): void
    {
        $this->chatterService->log($procedure, 'Procedure deleted.', 'system');
        $procedure->delete();
    }

    private function instantiateTickets(Procedure $procedure, ProcedureTemplate $template): void
    {
        $steps = $template->steps()->with(['inputs.options', 'inputs.guestSteps', 'nextSteps', 'pathChoices', 'subProcedures'])->get();

        // Map step_id → Ticket; step_id → [template_input_id → frozen_record_input_id]
        $ticketMap      = [];
        $frozenInputMap = [];

        foreach ($steps as $step) {
            $ticket = Ticket::create([
                'procedure_id'              => $procedure->id,
                'procedure_step_id'         => $step->id,
                'name'                      => $step->name,
                'description'               => $step->description,
                'state'                     => 'draft',
                'assigned_to_department_id' => $step->default_department_id,
                'has_procedures'            => $step->has_procedures,
                'procedures_required'       => $step->procedures_required,
                'has_path_choice'           => $step->has_path_choice,
                'path_choice_question'      => $step->path_choice_question,
                'path_choice_required'      => $step->path_choice_required,
                'ignore_state'              => $step->ignore_state,
                'is_approve_only'           => $step->is_approve_only,
            ]);

            foreach ($step->subProcedures as $subProc) {
                $ticket->procedureLines()->create([
                    'procedure_template_id' => $subProc->id,
                    'name'                  => $subProc->name,
                    'state'                 => 'pending',
                ]);
            }

            foreach ($step->inputs as $input) {
                $frozen = $ticket->inputs()->create([
                    'record_type'       => 'ticket',
                    'template_input_id' => $input->id,
                    'name'              => $input->name,
                    'type'              => $input->type,
                    'is_required'       => $input->is_required,
                ]);
                $frozenInputMap[$step->id][$input->id] = $frozen->id;
            }

            $ticketMap[$step->id] = $ticket;
        }

        foreach ($steps as $step) {
            foreach ($step->nextSteps as $nextStep) {
                if (isset($ticketMap[$step->id], $ticketMap[$nextStep->id])) {
                    $ticketMap[$step->id]->nextTickets()->attach($ticketMap[$nextStep->id]->id);
                    $ticketMap[$nextStep->id]->update(['previous_ticket_id' => $ticketMap[$step->id]->id]);
                }
            }

            foreach ($step->pathChoices as $pathChoice) {
                if (isset($ticketMap[$step->id], $ticketMap[$pathChoice->target_step_id])) {
                    $ticketMap[$step->id]->pathChoices()->create([
                        'name'             => $pathChoice->name,
                        'target_ticket_id' => $ticketMap[$pathChoice->target_step_id]->id,
                    ]);
                }
            }
        }

        // Freeze cross-ref visibility: inputs from step A that should show read-only in step B's ticket
        $crossRefs = [];
        foreach ($steps as $step) {
            foreach ($step->inputs as $input) {
                if ($input->guestSteps->isEmpty()) continue;
                $sourceId = $frozenInputMap[$step->id][$input->id] ?? null;
                if (!$sourceId) continue;
                foreach ($input->guestSteps as $guestStep) {
                    $viewingTicket = $ticketMap[$guestStep->id] ?? null;
                    if (!$viewingTicket) continue;
                    $crossRefs[] = [
                        'viewing_ticket_id'      => $viewingTicket->id,
                        'source_record_input_id' => $sourceId,
                    ];
                }
            }
        }
        if (!empty($crossRefs)) {
            DB::table('workflow_ticket_input_refs')->insertOrIgnore($crossRefs);
        }

        // Activate start tickets (no previous ticket)
        foreach ($ticketMap as $ticket) {
            $ticket->refresh();
            if ($ticket->previous_ticket_id === null) {
                $this->unlockSingleTicket($ticket);
            }
        }
    }

    private function unlockNextTickets(Ticket $ticket): void
    {
        // If a path was chosen, only unlock the chosen target; all others were already skipped.
        if ($ticket->has_path_choice && $ticket->path_chosen_id) {
            $chosenTargetId = $ticket->pathChoices()->find($ticket->path_chosen_id)?->target_ticket_id;
            foreach ($ticket->nextTickets as $next) {
                if ($next->id === $chosenTargetId) {
                    $this->unlockSingleTicket($next);
                }
                // unchosen next tickets stay skipped — not re-unlocked
            }
            return;
        }

        foreach ($ticket->nextTickets as $next) {
            $this->unlockSingleTicket($next);
        }
    }

    private function unlockSingleTicket(Ticket $ticket): void
    {
        $prev = $ticket->previous_ticket_id
            ? Ticket::find($ticket->previous_ticket_id)
            : null;

        $prevDone = !$prev || in_array($prev->state, ['completed', 'skipped']);

        // Unlock draft tickets on first activation, and rejected/skipped tickets when re-activated
        if ($prevDone && in_array($ticket->state, ['draft', 'rejected', 'skipped'])) {
            $ticket->update(['state' => 'pending']);
            $this->seedTicketViewers($ticket);
            $this->notifyTicketDepartment($ticket);
        }
    }

    private function rejectChainToTarget(Ticket $startTicket, int $targetId, ?string $reason): void
    {
        $cursor = $startTicket;
        $seen   = [$cursor->id => true];
        $iterations = 0;

        // Walk backwards from startTicket, rejecting each intermediate ticket,
        // until cursor's previous is the target (or we've gone far enough).
        while ($cursor->previous_ticket_id && $cursor->previous_ticket_id !== $targetId && $iterations < 100) {
            $iterations++;
            $prev = Ticket::find($cursor->previous_ticket_id);
            if (!$prev || isset($seen[$prev->id])) break; // cycle guard
            $seen[$prev->id] = true;

            // Reject siblings of cursor that are still active
            foreach ($prev->nextTickets as $sibling) {
                if ($sibling->id !== $cursor->id && !in_array($sibling->state, ['rejected', 'skipped'])) {
                    $sibling->update(['state' => 'rejected']);
                }
            }

            $prev->update(['state' => 'rejected']);
            $cursor = $prev;
        }

        $target = Ticket::find($targetId);
        if (!$target) return;

        // Defense-in-depth: the only caller (TicketController::close) already
        // validates that targetId is in startTicket's predecessor chain. But
        // the service should not trust that — if someone wires a new caller
        // without the chain-walk check, this would otherwise let a malicious
        // POST reactivate a ticket in a different procedure entirely.
        if ($target->procedure_id !== $startTicket->procedure_id) {
            return;
        }

        // Reject any remaining active next tickets of the target (cursor's siblings)
        foreach ($target->nextTickets as $nt) {
            if ($nt->id !== $cursor->id && !in_array($nt->state, ['rejected', 'skipped'])) {
                $nt->update(['state' => 'rejected']);
            }
        }

        $targetData = ['state' => 'pending'];
        if ($reason) {
            $targetData['return_reason'] = $reason;
        }
        $target->update($targetData);

        $isChain = $startTicket->previous_ticket_id !== $targetId;
        $msg = "Ticket '{$startTicket->name}' rejected"
            . ($isChain ? ' (chain return)' : '')
            . " — '{$target->name}' reactivated."
            . ($reason ? " Reason: {$reason}" : '');

        $this->chatterService->log($startTicket->procedure, $msg, 'system');
    }

    private function finishProcedureAsCompleted(Procedure $procedure): void
    {
        $procedure->update(['state' => 'completed']);
        $this->chatterService->log($procedure, 'Procedure completed.', 'system');

        if ($procedure->created_by_user_id && $procedure->created_by_user_id !== auth()->user()?->id) {
            $creator = User::find($procedure->created_by_user_id);
            $creator?->notify('Procedure completed: ' . $procedure->name, '', route('workflow.procedures.show', $procedure));
        }
    }

    private function finishProcedureAsCancelled(Procedure $procedure): void
    {
        $procedure->update(['state' => 'closed']);
        $this->chatterService->log($procedure, 'No active tickets remaining — procedure cancelled.', 'system');

        if ($procedure->created_by_user_id && $procedure->created_by_user_id !== auth()->user()?->id) {
            $creator = User::find($procedure->created_by_user_id);
            $creator?->notify('Procedure cancelled: ' . $procedure->name, '', route('workflow.procedures.show', $procedure));
        }
    }

    private function seedTicketViewers(Ticket $ticket): void
    {
        if (!$ticket->assigned_to_department_id) return;

        $userIds = WorkflowUser::where('default_department_id', $ticket->assigned_to_department_id)
            ->where('active', true)
            ->pluck('user_id')
            ->toArray();

        if (empty($userIds)) return;

        $ticket->viewers()->syncWithoutDetaching($userIds);

        if ($ticket->procedure_id) {
            $ticket->procedure->viewers()->syncWithoutDetaching($userIds);
        }
    }

    private function notifyTicketDepartment(Ticket $ticket): void
    {
        if (!$ticket->assigned_to_department_id) {
            return;
        }

        $procedure = $ticket->procedure;
        $actingId  = auth()->user()?->id;

        $users = WorkflowUser::where('default_department_id', $ticket->assigned_to_department_id)
            ->where('active', true)
            ->with('user')
            ->get();

        foreach ($users as $wu) {
            $user = $wu->user;
            if ($user && $user->id !== $actingId) {
                $user->notify(
                    "Ticket ready: {$ticket->name}",
                    $procedure ? "Procedure: {$procedure->name}" : '',
                    $procedure ? route('workflow.procedures.show', $procedure) : ''
                );
            }
        }
    }

    private function checkProcedureCompletion(Procedure $procedure): void
    {
        $procedure->refresh();
        // ignore_state tickets are excluded — they never block or drive procedure completion
        $tickets  = $procedure->tickets->where('ignore_state', false);
        $terminal = ['completed', 'rejected', 'skipped'];
        $allDone  = $tickets->every(fn (Ticket $t) => in_array($t->state, $terminal));

        if (!$allDone || $procedure->state !== 'pending') {
            return;
        }

        $anyCompleted = $tickets->contains(fn (Ticket $t) => $t->state === 'completed');

        if ($anyCompleted) {
            $procedure->update(['state' => 'completed']);
            $this->chatterService->log($procedure, 'All tickets completed — procedure marked as completed.', 'system');

            if ($procedure->created_by_user_id && $procedure->created_by_user_id !== auth()->user()?->id) {
                $creator = User::find($procedure->created_by_user_id);
                $creator?->notify(
                    "Procedure completed: {$procedure->name}",
                    '',
                    route('workflow.procedures.show', $procedure)
                );
            }
        } else {
            $procedure->update(['state' => 'closed']);
            $this->chatterService->log($procedure, 'All tickets rejected/skipped — procedure closed.', 'system');

            if ($procedure->created_by_user_id && $procedure->created_by_user_id !== auth()->user()?->id) {
                $creator = User::find($procedure->created_by_user_id);
                $creator?->notify(
                    "Procedure closed: {$procedure->name}",
                    '',
                    route('workflow.procedures.show', $procedure)
                );
            }
        }
    }
}
