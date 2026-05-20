<?php

namespace App\Services\Workflow;

use App\Models\Chat\ChatRoom;
use App\Models\User;
use App\Models\Workflow\Ticket;
use App\Models\Workflow\TicketTemplate;
use App\Models\Workflow\WorkflowTemplateInput;
use App\Models\Workflow\WorkflowUser;
use App\Services\Chatter\ChatterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TicketService
{
    public function __construct(
        private readonly ChatterService $chatterService,
        private readonly ProcedureService $procedureService,
    ) {}

    public function create(array $data, TicketTemplate $template): Ticket
    {
        // Assign template's default department when none provided
        if (empty($data['assigned_to_department_id']) && $template->default_department_id) {
            $data['assigned_to_department_id'] = $template->default_department_id;
        }

        $ticket = Ticket::create(array_merge($data, [
            'template_id'          => $template->id,
            'state'                => 'pending',
            'resolve_max_duration' => $template->resolve_max_duration,
            'created_by_user_id'   => auth()->user()?->id,
            'resolve_deadline'     => $template->resolve_max_duration
                ? now()->addHours($template->resolve_max_duration)
                : null,
        ]));

        $this->chatterService->logCreated($ticket, 'Ticket');

        // Create a dedicated chat room for this ticket
        $room = ChatRoom::create([
            'name'               => $ticket->name,
            'description'        => 'Ticket #' . $ticket->id,
            'created_by_user_id' => auth()->user()?->id,
            'type'               => 'ticket',
        ]);
        $ticket->update(['chat_room_id' => $room->id]);

        // Seed viewers: creator + all dept members
        $viewerIds = [auth()->user()?->id];
        if ($ticket->assigned_to_department_id) {
            $viewerIds = array_unique(array_merge(
                $viewerIds,
                $this->deptUserIds($ticket->assigned_to_department_id)
            ));
        }
        $ticket->viewers()->sync($viewerIds);

        if (!empty($ticket->assigned_to_user_id)) {
            $assignee = User::find($ticket->assigned_to_user_id);
            if ($assignee && $assignee->id !== auth()->user()?->id) {
                $assignee->notify(
                    "New ticket assigned: {$ticket->name}",
                    $ticket->description ?? '',
                    route('workflow.tickets.show', $ticket)
                );
            }
        }

        return $ticket;
    }

    public function update(Ticket $ticket, array $data): Ticket
    {
        $changes = $this->detectChanges($ticket, $data);

        $oldDeptId    = $ticket->assigned_to_department_id;
        $newDeptId    = $data['assigned_to_department_id'] ?? null;
        $prevAssignee = $ticket->assigned_to_user_id;

        $ticket->update($data);

        if (!empty($changes)) {
            $this->chatterService->logUpdated($ticket, $changes, 'Ticket');
        }

        // New department: add all its members to viewers
        if ($newDeptId && $newDeptId != $oldDeptId) {
            $ticket->viewers()->syncWithoutDetaching($this->deptUserIds($newDeptId));
        }

        // Ensure newly assigned user is always a viewer
        $newAssigneeId = $data['assigned_to_user_id'] ?? null;
        if ($newAssigneeId) {
            $ticket->viewers()->syncWithoutDetaching([$newAssigneeId]);

            if ($newAssigneeId !== $prevAssignee) {
                $assignee = User::find($newAssigneeId);
                if ($assignee && $assignee->id !== auth()->user()?->id) {
                    $assignee->notify(
                        "Ticket assigned to you: {$ticket->name}",
                        '',
                        route('workflow.tickets.show', $ticket)
                    );
                }
            }
        }

        return $ticket->fresh();
    }

    public function addViewer(Ticket $ticket, User $user): void
    {
        $ticket->viewers()->syncWithoutDetaching([$user->id]);
    }

    public function removeViewer(Ticket $ticket, User $user): void
    {
        // Never remove the creator
        if ($user->id === $ticket->created_by_user_id) return;

        $ticket->viewers()->detach($user->id);
    }

    public function resolve(Ticket $ticket): Ticket
    {
        if ($ticket->procedure_id) {
            $this->procedureService->completeTicket($ticket);
            return $ticket->fresh();
        }

        $duration = (int) round(now()->diffInHours($ticket->created_at, true));
        $passed   = max(0, $duration - ($ticket->resolve_max_duration ?? 0));

        $ticket->update([
            'state'                   => 'completed',
            'resolve_duration'        => $duration,
            'resolve_deadline_passed' => $passed,
        ]);
        $this->chatterService->log($ticket, 'Ticket marked as completed.', 'system');

        if ($ticket->created_by_user_id && $ticket->created_by_user_id !== auth()->user()?->id) {
            $creator = User::find($ticket->created_by_user_id);
            $creator?->notify(
                "Ticket resolved: {$ticket->name}",
                '',
                route('workflow.tickets.show', $ticket)
            );
        }

        return $ticket;
    }

    public function close(Ticket $ticket, ?string $reason = null, ?int $returnToTicketId = null): Ticket
    {
        if ($ticket->procedure_id) {
            $this->procedureService->rejectTicket($ticket, $reason, $returnToTicketId);
            return $ticket->fresh();
        }

        $ticket->update(['state' => 'closed']);
        $this->chatterService->log($ticket, 'Ticket closed.', 'system');

        if ($ticket->created_by_user_id && $ticket->created_by_user_id !== auth()->user()?->id) {
            $creator = User::find($ticket->created_by_user_id);
            $creator?->notify(
                "Ticket closed: {$ticket->name}",
                '',
                route('workflow.tickets.show', $ticket)
            );
        }

        return $ticket;
    }

    public function reopen(Ticket $ticket): Ticket
    {
        $ticket->update(['state' => 'pending']);
        $this->chatterService->log($ticket, 'Ticket reopened.', 'system');

        return $ticket;
    }

    public function archive(Ticket $ticket): Ticket
    {
        $ticket->update(['active' => false]);
        $this->chatterService->logArchived($ticket, 'Ticket');

        return $ticket;
    }

    public function unarchive(Ticket $ticket): Ticket
    {
        $ticket->update(['active' => true]);
        $this->chatterService->logUnarchived($ticket, 'Ticket');

        return $ticket;
    }

    public function delete(Ticket $ticket): void
    {
        $this->chatterService->log($ticket, 'Ticket deleted.', 'system');
        $ticket->delete();
    }

    public function saveInputValue(Ticket $ticket, int $templateInputId, array $valueData): void
    {
        $templateInput = WorkflowTemplateInput::find($templateInputId);
        if (!$templateInput) return;

        // Pull out multiselect option IDs — handled separately via pivot, not a column
        $selectedOptionIds = null;
        if (array_key_exists('_selected_option_ids', $valueData)) {
            $selectedOptionIds = $valueData['_selected_option_ids'];
            unset($valueData['_selected_option_ids']);
        }

        // Delete old file from storage when a new one replaces it
        if (array_key_exists('value_file_path', $valueData) && $valueData['value_file_path']) {
            $existing = $ticket->inputs()->where('template_input_id', $templateInputId)->first();
            if ($existing?->value_file_path && $existing->value_file_path !== $valueData['value_file_path']) {
                Storage::disk('local')->delete($existing->value_file_path);
            }
        }

        $record = $ticket->inputs()->updateOrCreate(
            ['template_input_id' => $templateInputId],
            array_merge([
                'name'        => $templateInput->name,
                'type'        => $templateInput->type,
                'record_type' => 'ticket',
            ], $valueData)
        );

        if ($selectedOptionIds !== null) {
            $record->selectedOptions()->sync($selectedOptionIds);
        }
    }

    private function deptUserIds(int $deptId): array
    {
        return WorkflowUser::where('default_department_id', $deptId)
            ->where('active', true)
            ->pluck('user_id')
            ->toArray();
    }

    private function detectChanges(Ticket $ticket, array $data): array
    {
        $changes = [];
        $tracked = $ticket->chatterTracked ?? [];

        foreach ($tracked as $field => $definition) {
            if (!array_key_exists($field, $data)) continue;

            $old = (string) ($ticket->{$field} ?? '');
            $new = (string) ($data[$field] ?? '');
            if ($old === $new) continue;

            $label  = is_array($definition) ? $definition['label']            : $definition;
            $table  = is_array($definition) ? ($definition['table']  ?? null) : null;
            $column = is_array($definition) ? ($definition['column'] ?? 'name') : null;

            $changes[] = [
                'field' => $field,
                'label' => $label,
                'from'  => $this->resolveDisplay($old ?: null, $table, $column),
                'to'    => $this->resolveDisplay($new ?: null, $table, $column),
            ];
        }

        return $changes;
    }

    private function resolveDisplay(?string $id, ?string $table, ?string $column = null): string
    {
        if ($id === null || $id === '') return '—';
        if (!$table) return $id;

        $row = DB::table($table)->where('id', $id)->first();
        if (!$row) return $id;

        // Explicit column → use it; otherwise try name → title → id
        if ($column) return (string) ($row->{$column} ?? $id);
        return (string) ($row->name ?? $row->title ?? $id);
    }
}
