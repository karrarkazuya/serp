<?php

namespace Database\Seeders;

use App\Models\Settings\Company;
use App\Models\User;
use App\Models\Workflow\Department;
use App\Models\Workflow\Group;
use App\Models\Workflow\Manager;
use App\Models\Workflow\ProcedureStep;
use App\Models\Workflow\ProcedureTemplate;
use App\Models\Workflow\TicketTemplate;
use App\Models\Workflow\WorkflowTemplateInput;
use App\Models\Workflow\WorkflowUser;
use Illuminate\Database\Seeder;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $company = Company::first();

        // Groups
        $allGroup  = Group::updateOrCreate(['name' => 'All Staff'],   ['active' => true]);
        $mgmtGroup = Group::updateOrCreate(['name' => 'Management'],  ['active' => true]);

        // Departments
        $itDept = Department::updateOrCreate(
            ['name' => 'IT'],
            ['company_id' => $company?->id, 'active' => true]
        );
        $hrDept = Department::updateOrCreate(
            ['name' => 'Human Resources'],
            ['company_id' => $company?->id, 'active' => true]
        );

        // Workflow users
        if ($admin) {
            $wu = WorkflowUser::updateOrCreate(
                ['user_id' => $admin->id],
                ['default_department_id' => $itDept->id, 'active' => true]
            );
            $wu->groups()->syncWithoutDetaching([$allGroup->id, $mgmtGroup->id]);
            $wu->assignableDepartments()->syncWithoutDetaching([$itDept->id, $hrDept->id]);

            // Manager
            $manager = Manager::firstOrCreate(['workflow_user_id' => $wu->id], ['active' => true]);
            $manager->departments()->syncWithoutDetaching([$itDept->id, $hrDept->id]);
        }

        // Ticket Template
        $ticketTpl = TicketTemplate::updateOrCreate(
            ['name' => 'IT Support Request'],
            [
                'description'          => 'General IT support ticket.',
                'default_group_id'     => $allGroup->id,
                'default_department_id'=> $itDept->id,
                'resolve_max_duration' => 48,
                'enabled'              => true,
                'active'               => true,
            ]
        );
        $ticketTpl->departments()->syncWithoutDetaching([$itDept->id, $hrDept->id]);

        WorkflowTemplateInput::firstOrCreate(
            ['owner_id' => $ticketTpl->id, 'owner_type' => 'ticket_template', 'name' => 'Problem Description'],
            ['type' => 'char', 'is_required' => true, 'sort_order' => 1]
        );

        $catInput = WorkflowTemplateInput::firstOrCreate(
            ['owner_id' => $ticketTpl->id, 'owner_type' => 'ticket_template', 'name' => 'Category'],
            ['type' => 'select', 'is_required' => true, 'sort_order' => 2]
        );
        foreach (['Hardware', 'Software', 'Network', 'Account'] as $opt) {
            $catInput->options()->firstOrCreate(['name' => $opt]);
        }

        // Procedure Template
        $procTpl = ProcedureTemplate::updateOrCreate(
            ['name' => 'Employee Onboarding'],
            [
                'description'          => 'Standard new employee onboarding procedure.',
                'default_group_id'     => $mgmtGroup->id,
                'resolve_max_duration' => 168,
                'creator_see_tasks'    => true,
                'enabled'              => true,
                'active'               => true,
            ]
        );
        $procTpl->departments()->syncWithoutDetaching([$hrDept->id]);

        if ($procTpl->steps()->count() === 0) {
            $step1 = ProcedureStep::create([
                'procedure_template_id' => $procTpl->id,
                'name'                  => 'Prepare Workstation',
                'description'           => 'Set up laptop, accounts, and equipment.',
                'task_sequence'         => 1,
                'default_department_id' => $itDept->id,
                'resolve_max_duration'  => 24,
                'enabled'               => true,
            ]);
            WorkflowTemplateInput::create([
                'owner_id'    => $step1->id,
                'owner_type'  => 'procedure_step',
                'name'        => 'Equipment Serial Number',
                'type'        => 'char',
                'is_required' => true,
                'sort_order'  => 1,
            ]);

            $step2 = ProcedureStep::create([
                'procedure_template_id' => $procTpl->id,
                'name'                  => 'HR Documentation',
                'description'           => 'Collect signed contracts and ID documents.',
                'task_sequence'         => 2,
                'default_department_id' => $hrDept->id,
                'resolve_max_duration'  => 48,
                'enabled'               => true,
            ]);
            $docInput = WorkflowTemplateInput::create([
                'owner_id'    => $step2->id,
                'owner_type'  => 'procedure_step',
                'name'        => 'Documents Status',
                'type'        => 'select',
                'is_required' => true,
                'sort_order'  => 1,
            ]);
            foreach (['Complete', 'Partial', 'Pending'] as $opt) {
                $docInput->options()->create(['name' => $opt]);
            }

            $step3 = ProcedureStep::create([
                'procedure_template_id' => $procTpl->id,
                'name'                  => 'Orientation Complete',
                'description'           => 'Confirm employee completed orientation session.',
                'task_sequence'         => 3,
                'default_department_id' => $hrDept->id,
                'resolve_max_duration'  => 8,
                'enabled'               => true,
            ]);
            WorkflowTemplateInput::create([
                'owner_id'    => $step3->id,
                'owner_type'  => 'procedure_step',
                'name'        => 'Completion Notes',
                'type'        => 'char',
                'is_required' => false,
                'sort_order'  => 1,
            ]);

            // Chain: step1 → step2 → step3
            $step1->nextSteps()->attach($step2->id);
            $step2->nextSteps()->attach($step3->id);
        }
    }
}
