<?php

namespace App\Services\Workflow;

use App\Models\Workflow\Department;
use App\Models\Workflow\Group;
use App\Models\Workflow\ProcedureTemplate;
use App\Models\Workflow\TicketTemplate;
use App\Models\Workflow\WorkflowTemplateInput;
use App\Models\Workflow\WorkflowUser;
use App\Services\Chatter\ChatterService;

class WorkflowConfigService
{
    public function __construct(
        private readonly ChatterService $chatterService
    ) {}

    // Groups
    public function createGroup(array $data): Group
    {
        $group = Group::create($data);
        $this->chatterService->logCreated($group, 'Group');
        return $group;
    }

    public function updateGroup(Group $group, array $data): Group
    {
        $group->update($data);
        return $group->fresh();
    }

    public function deleteGroup(Group $group): void
    {
        $group->delete();
    }

    // Departments
    public function createDepartment(array $data): Department
    {
        $dept = Department::create($data);
        $this->chatterService->logCreated($dept, 'Department');
        return $dept;
    }

    public function updateDepartment(Department $dept, array $data): Department
    {
        $dept->update($data);
        return $dept->fresh();
    }

    public function deleteDepartment(Department $dept): void
    {
        $dept->delete();
    }

    // Workflow Users
    public function updateWorkflowUser(WorkflowUser $wu, array $data, array $groupIds, array $deptIds): WorkflowUser
    {
        $changes = [];

        // Scalar field diffs
        if (array_key_exists('active', $data) && (bool) $data['active'] !== (bool) $wu->active) {
            $changes[] = 'Active: ' . ($wu->active ? 'Yes' : 'No') . ' → ' . ($data['active'] ? 'Yes' : 'No');
        }

        if (array_key_exists('default_department_id', $data) && $data['default_department_id'] != $wu->default_department_id) {
            $oldDept = $wu->defaultDepartment?->name ?? '—';
            $newDept = Department::find($data['default_department_id'])?->name ?? '—';
            $changes[] = "Default Dept.: {$oldDept} → {$newDept}";
        }

        // Groups diff — compare sorted name strings directly
        $oldGroupNames = $wu->groups()->orderBy('name')->pluck('name')->join(', ') ?: '—';
        $newGroupNames = Group::whereIn('id', $groupIds)->orderBy('name')->pluck('name')->join(', ') ?: '—';
        if ($oldGroupNames !== $newGroupNames) {
            $changes[] = "Groups: {$oldGroupNames} → {$newGroupNames}";
        }

        // Assignable departments diff — compare sorted name strings directly
        $oldDeptNames = $wu->assignableDepartments()->orderBy('name')->pluck('name')->join(', ') ?: '—';
        $newDeptNames = Department::whereIn('id', $deptIds)->orderBy('name')->pluck('name')->join(', ') ?: '—';
        if ($oldDeptNames !== $newDeptNames) {
            $changes[] = "Assignable Depts.: {$oldDeptNames} → {$newDeptNames}";
        }

        $wu->update($data);
        $wu->groups()->sync($groupIds);
        $wu->assignableDepartments()->sync($deptIds);

        foreach ($changes as $change) {
            $this->chatterService->log($wu, $change, 'system');
        }

        return $wu->fresh();
    }

    public function ensureWorkflowUser(\App\Models\User $user): WorkflowUser
    {
        return WorkflowUser::firstOrCreate(
            ['user_id' => $user->id],
            ['active' => true]
        );
    }

    // Ticket Templates
    public function createTicketTemplate(array $data, array $deptIds): TicketTemplate
    {
        $tpl = TicketTemplate::create($data);
        $tpl->departments()->sync($deptIds);
        $this->chatterService->logCreated($tpl, 'Ticket Template');
        return $tpl;
    }

    public function updateTicketTemplate(TicketTemplate $tpl, array $data, array $deptIds): TicketTemplate
    {
        $tpl->update($data);
        $tpl->departments()->sync($deptIds);
        return $tpl->fresh();
    }

    public function syncTicketTemplateInputs(TicketTemplate $tpl, array $inputsData): void
    {
        $this->syncInputs($tpl->id, 'ticket_template', $tpl->inputs(), $inputsData);
    }

    public function syncProcedureStepInputs(\App\Models\Workflow\ProcedureStep $step, array $inputsData): void
    {
        $this->syncInputs($step->id, 'procedure_step', $step->inputs(), $inputsData);
    }

    private function syncInputs(int $ownerId, string $ownerType, \Illuminate\Database\Eloquent\Relations\HasMany $query, array $inputsData): void
    {
        $submittedIds = [];

        foreach ($inputsData as $i => $row) {
            if (empty($row['name']) || ($row['type'] ?? '') === '') continue;

            $type    = in_array($row['type'], WorkflowTemplateInput::TYPES) ? $row['type'] : 'char';
            $options = $type === 'select'
                ? array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $row['options'] ?? ''))))
                : [];

            $attrs = [
                'owner_id'    => $ownerId,
                'owner_type'  => $ownerType,
                'name'        => $row['name'],
                'type'        => $type,
                'is_required' => (bool) ($row['is_required'] ?? false),
                'sort_order'  => (int) ($row['sort_order'] ?? $i),
                'active'      => true,
            ];

            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $input = WorkflowTemplateInput::find($id);
                if ($input && $input->owner_id === $ownerId && $input->owner_type === $ownerType) {
                    $input->update($attrs);
                    $input->options()->delete();
                    foreach ($options as $opt) {
                        $input->options()->create(['name' => $opt]);
                    }
                    $submittedIds[] = $input->id;
                    continue;
                }
            }

            $input = WorkflowTemplateInput::create($attrs);
            foreach ($options as $opt) {
                $input->options()->create(['name' => $opt]);
            }
            $submittedIds[] = $input->id;
        }

        $query->whereNotIn('id', $submittedIds ?: [0])->delete();
    }

    public function deleteTicketTemplate(TicketTemplate $tpl): void
    {
        $tpl->delete();
    }

    // Procedure Templates
    public function createProcedureTemplate(array $data, array $deptIds): ProcedureTemplate
    {
        $tpl = ProcedureTemplate::create($data);
        $tpl->departments()->sync($deptIds);
        $this->chatterService->logCreated($tpl, 'Procedure Template');
        return $tpl;
    }

    public function updateProcedureTemplate(ProcedureTemplate $tpl, array $data, array $deptIds): ProcedureTemplate
    {
        $tpl->update($data);
        $tpl->departments()->sync($deptIds);
        return $tpl->fresh();
    }

    public function deleteProcedureTemplate(ProcedureTemplate $tpl): void
    {
        $tpl->delete();
    }
}
