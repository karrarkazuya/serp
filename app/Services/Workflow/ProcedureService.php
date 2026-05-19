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
            'resolve_max_duration'  => $template->resolve_max_duration,
            'created_by_user_id'    => $actorId,
            'resolve_deadline'      => $template->resolve_max_duration
                ? now()->addHours($template->resolve_max_duration)
                : null,
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
        DB::transaction(function () use ($ticket) {
            $ticket->update(['state' => 'completed']);
            $this->chatterService->log($ticket->procedure, "Ticket '{$ticket->name}' completed.", 'system');

            $this->unlockNextTickets($ticket);
            $this->checkProcedureCompletion($ticket->procedure);
        });
    }

    public function rejectTicket(Ticket $ticket): void
    {
        DB::transaction(function () use ($ticket) {
            $ticket->update(['state' => 'rejected']);
            $this->chatterService->log($ticket->procedure, "Ticket '{$ticket->name}' rejected.", 'system');
            $this->checkProcedureCompletion($ticket->procedure);
        });
    }

    public function skipTicket(Ticket $ticket): void
    {
        DB::transaction(function () use ($ticket) {
            $ticket->update(['state' => 'skipped']);
            $this->chatterService->log($ticket->procedure, "Ticket '{$ticket->name}' skipped.", 'system');

            $this->unlockNextTickets($ticket);
            $this->checkProcedureCompletion($ticket->procedure);
        });
    }

    public function choosePath(Ticket $ticket, TicketPath $path): void
    {
        DB::transaction(function () use ($ticket, $path) {
            $ticket->update(['path_chosen_id' => $path->id]);

            foreach ($ticket->pathChoices as $p) {
                $target = $p->targetTicket;
                if (!$target) continue;

                if ($p->id === $path->id) {
                    $this->unlockSingleTicket($target);
                } else {
                    $target->update(['state' => 'skipped']);
                }
            }
        });
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
        $actorId = auth()->user()?->id;
        $steps = $template->steps()->with(['inputs.options', 'nextSteps', 'pathChoices', 'subProcedures'])->get();

        // Map step_id → Ticket
        $ticketMap = [];
        foreach ($steps as $step) {
            $ticket = Ticket::create([
                'procedure_id'              => $procedure->id,
                'procedure_step_id'         => $step->id,
                'name'                      => $step->name,
                'description'               => $step->description,
                'state'                     => 'draft',
                'task_sequence'             => $step->task_sequence,
                'assigned_to_department_id' => $step->default_department_id,
                'has_procedures'            => $step->has_procedures,
                'has_path_choice'           => $step->has_path_choice,
                'path_choice_question'      => $step->path_choice_question,
                'ignore_state'              => $step->ignore_state,
                'is_approve_only'           => $step->is_approve_only,
                'resolve_max_duration'      => $step->resolve_max_duration,
                'resolve_deadline'          => $step->resolve_max_duration
                    ? now()->addHours($step->resolve_max_duration)
                    : null,
            ]);

            foreach ($step->subProcedures as $subProc) {
                $ticket->procedureLines()->create([
                    'procedure_template_id' => $subProc->id,
                    'name'                  => $subProc->name,
                    'state'                 => 'pending',
                ]);
            }

            foreach ($step->inputs as $input) {
                $ticket->inputs()->create([
                    'record_type'       => 'ticket',
                    'template_input_id' => $input->id,
                    'name'              => $input->name,
                    'type'              => $input->type,
                    'is_required'       => $input->is_required,
                ]);
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

        if ($prevDone && $ticket->state === 'draft') {
            $ticket->update(['state' => 'pending']);
            $this->seedTicketViewers($ticket);
            $this->notifyTicketDepartment($ticket);
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
        $tickets  = $procedure->tickets;
        $terminal = ['completed', 'rejected', 'skipped'];
        $allDone  = $tickets->every(fn (Ticket $t) => in_array($t->state, $terminal));

        if (!$allDone || $procedure->state !== 'pending') {
            return;
        }

        $anyCompleted = $tickets->contains(fn (Ticket $t) => $t->state === 'completed');
        $duration     = (int) round(now()->diffInHours($procedure->created_at, true));

        if ($anyCompleted) {
            $passed = max(0, $duration - ($procedure->resolve_max_duration ?? 0));
            $procedure->update([
                'state'                   => 'completed',
                'resolve_duration'        => $duration,
                'resolve_deadline_passed' => $passed,
            ]);
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
            $procedure->update([
                'state'            => 'closed',
                'resolve_duration' => $duration,
            ]);
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
