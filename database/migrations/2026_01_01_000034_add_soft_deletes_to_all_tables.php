<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Tables that already have softDeletes: workflow_procedures, hr_employees, contacts, workflow_procedure_templates, workflow_procedure_steps
    // Tables excluded (framework internals or pure composite-PK pivots):
    //   sessions, cache, cache_locks, jobs, job_batches, failed_jobs,
    //   password_reset_tokens, personal_access_tokens,
    //   contact_tag, role_permission, user_role, user_company,
    //   workflow_user_group, workflow_user_dept_assign, workflow_manager_department,
    //   workflow_procedure_viewers, workflow_ticket_next,
    //   workflow_ticket_template_department, workflow_procedure_template_department,
    //   workflow_procedure_step_next, workflow_procedure_step_sub_proc,
    //   workflow_record_input_multiselect, hr_employee_category_rel,
    //   account_move_line_taxes, inventory_product_routes, inventory_category_routes

    private array $tables = [
        // Core
        'users',
        'companies',
        'roles',
        'permissions',
        // Contacts
        'tags',
        // 'contacts' — already has softDeletes from create_contacts_tables migration
        'contact_phones',
        // Core misc
        'chatter_messages',
        'settings',
        'notifications',
        'files',
        // Chat
        'chat_rooms',
        'chat_messages',
        'chat_message_files',
        'chat_room_members',
        // Workflow base
        'workflow_groups',
        'workflow_users',
        'workflow_managers',
        // Workflow templates
        'workflow_ticket_templates',
        // 'workflow_procedure_templates' — already has softDeletes from migration 000015
        // 'workflow_procedure_steps'     — already has softDeletes from migration 000015
        'workflow_procedure_step_paths',
        // Workflow instances
        'workflow_tickets',
        'workflow_ticket_durations',
        'workflow_ticket_paths',
        'workflow_ticket_procedure_lines',
        'workflow_shared_links',
        'workflow_allowed_users',
        // Workflow inputs
        'workflow_template_inputs',
        'workflow_template_input_options',
        'workflow_record_inputs',
        // HR base
        'hr_departure_reasons',
        'hr_employee_categories',
        'hr_departments',
        'hr_jobs',
        'hr_work_locations',
        'hr_resource_calendars',
        'hr_resource_calendar_attendances',
        // HR skills
        'hr_skill_types',
        'hr_skills',
        'hr_skill_levels',
        // HR employee relations
        'hr_employee_skills',
        // HR contracts & details
        'hr_contracts',
        'hr_employee_documents',
        'hr_employee_bank_accounts',
        'hr_employee_emergency_contacts',
        'hr_employee_dependents',
        // HR misc
        'hr_resume_line_types',
        'hr_employment_types',
        'hr_badges',
        'hr_challenges',
        'hr_goals',
        // Accounting
        'accounts',
        'account_journals',
        'account_moves',
        'account_move_lines',
        'account_payments',
        'account_partial_reconciles',
        'account_taxes',
        'accounting_payment_terms',
        'accounting_payment_term_lines',
        'accounting_incoterms',
        'accounting_tax_groups',
        'accounting_account_groups',
        'currency_rates',
        // Inventory base
        'inventory_uom_categories',
        'inventory_uoms',
        'inventory_product_categories',
        'inventory_products',
        'inventory_product_suppliers',
        // Inventory warehouse
        'inventory_locations',
        'inventory_warehouses',
        'inventory_operation_types',
        'inventory_routes',
        'inventory_route_rules',
        'inventory_putaway_rules',
        // Inventory stock
        'inventory_lots',
        'inventory_pickings',
        'inventory_moves',
        'inventory_move_lines',
        'inventory_quants',
        'inventory_scrap_orders',
        'inventory_reorder_rules',
        'inventory_adjustments',
        'inventory_adjustment_lines',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropSoftDeletes();
            });
        }
    }
};
