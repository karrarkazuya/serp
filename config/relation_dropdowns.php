<?php

return [
    'tags' => [
        'read' => 'contacts.read',
        'write' => 'contacts.write',
        'create_permission' => 'contacts.write',
        'route' => 'contacts.tags.index',
        'create' => 'contacts.tags.create',
        'color' => 'color',
        'fields' => ['name'],
    ],

    'contacts' => [
        'read' => 'contacts.read',
        'write' => 'contacts.write',
        'create_permission' => 'contacts.create',
        'route' => 'contacts.index',
        'create' => 'contacts.create',
        'color' => null,
        'fields' => ['name', 'email'],
    ],

    'contacts_customers' => [
        'table' => 'contacts',
        'read' => 'contacts.read',
        'write' => 'contacts.write',
        'create_permission' => 'contacts.create',
        'route' => 'contacts.index',
        'create' => 'contacts.create',
        'color' => null,
        'fields' => ['name', 'email'],
        'where' => [['contact_type', '=', 'individual']],
    ],

    'contacts_vendors' => [
        'table' => 'contacts',
        'read' => 'contacts.read',
        'write' => 'contacts.write',
        'create_permission' => 'contacts.create',
        'route' => 'contacts.index',
        'create' => 'contacts.create',
        'color' => null,
        'fields' => ['name', 'email'],
        'where' => [['contact_type', '=', 'company']],
    ],

    'companies' => [
        'read' => 'companies.read',
        'write' => 'companies.write',
        'create_permission' => 'companies.create',
        'route' => 'settings.companies.index',
        'create' => 'settings.companies.create',
        'color' => null,
        'fields' => ['name', 'email'],
    ],

    'users' => [
        'read' => 'users.read',
        'write' => 'users.write',
        'create_permission' => 'users.create',
        'route' => 'settings.users.index',
        'create' => 'settings.users.create',
        'color' => null,
        'fields' => ['name', 'email'],
    ],

    // Open to any authenticated user — used by the chat group member picker.
    // 'table' maps the logical key to the real DB table so the lookup URL stays simple.
    'chat_users' => [
        'table'  => 'users',
        'open'   => true,        // skip permission check — auth middleware is sufficient
        'read'   => null,
        'write'  => null,
        'create_permission' => null,
        'route'  => null,
        'create' => null,
        'color'  => null,
        'fields' => ['name'],
        'active_only' => true,   // filter inactive users out of results
    ],

    'workflow_groups' => [
        'read' => 'workflow.config.read',
        'write' => 'workflow.config.write',
        'create_permission' => 'workflow.config.write',
        'route' => 'workflow.config.groups.index',
        'create' => 'workflow.config.groups.create',
        'color' => null,
        'fields' => ['name'],
    ],

    'workflow_users' => [
        'read' => 'workflow.config.read',
        'write' => 'workflow.config.write',
        'create_permission' => 'workflow.config.write',
        'route' => 'workflow.config.users.index',
        'color' => null,
        'fields' => ['name', 'email'],
        'value_column' => 'user_id',
        'label_join' => [
            'table' => 'users',
            'local' => 'user_id',
            'foreign' => 'id',
            'fields' => ['name', 'email'],
        ],
    ],

    'workflow_ticket_templates' => [
        'read' => 'workflow.config.read',
        'write' => 'workflow.config.write',
        'create_permission' => 'workflow.config.write',
        'route' => 'workflow.config.ticket-templates.index',
        'create' => 'workflow.config.ticket-templates.create',
        'color' => null,
        'fields' => ['name'],
        'model' => \App\Models\Workflow\TicketTemplate::class,
        'visible_to_workflow_user' => true,
    ],

    'workflow_procedures' => [
        'read' => 'workflow.procedures.read',
        'write' => 'workflow.procedures.write',
        'create_permission' => 'workflow.procedures.create',
        'route' => 'workflow.procedures.index',
        'create' => 'workflow.procedures.create',
        'color' => null,
        'fields' => ['name'],
    ],

    'workflow_procedure_templates' => [
        'read' => 'workflow.config.read',
        'write' => 'workflow.config.write',
        'create_permission' => 'workflow.config.write',
        'route' => 'workflow.config.procedure-templates.index',
        'create' => 'workflow.config.procedure-templates.create',
        'color' => null,
        'fields' => ['name'],
        'model' => \App\Models\Workflow\ProcedureTemplate::class,
        'visible_to_workflow_user' => true,
    ],

    'hr_employees' => [
        'read' => 'employees.read',
        'write' => 'employees.write',
        'create_permission' => 'employees.create',
        'route' => 'employees.index',
        'create' => 'employees.create',
        'color' => null,
        'fields' => ['name', 'work_email'],
    ],

    'hr_departments' => [
        'read' => 'employees.read',
        'write' => 'employees.write',
        'create_permission' => 'employees.create',
        'route' => 'employees.departments.index',
        'create' => 'employees.departments.create',
        'color' => null,
        'fields' => ['name'],
    ],

    'hr_jobs' => [
        'read' => 'employees.read',
        'write' => 'employees.write',
        'create_permission' => 'employees.create',
        'route' => 'employees.jobs.index',
        'create' => 'employees.jobs.create',
        'color' => null,
        'fields' => ['name'],
    ],

    'hr_work_locations' => [
        'read' => 'employees.read',
        'write' => 'employees.write',
        'create_permission' => 'employees.create',
        'route' => 'employees.work-locations.index',
        'create' => 'employees.work-locations.create',
        'color' => null,
        'fields' => ['name', 'address'],
    ],

    'hr_resource_calendars' => [
        'read' => 'employees.read',
        'write' => 'employees.write',
        'create_permission' => 'employees.create',
        'route' => 'employees.schedules.index',
        'create' => 'employees.schedules.create',
        'color' => null,
        'fields' => ['name'],
    ],

    'hr_employee_categories' => [
        'read' => 'employees.read',
        'write' => 'employees.write',
        'create_permission' => 'employees.create',
        'route' => 'employees.categories.index',
        'create' => 'employees.categories.create',
        'color' => 'color',
        'fields' => ['name'],
    ],

    'hr_departure_reasons' => [
        'read' => 'employees.read',
        'write' => 'employees.write',
        'create_permission' => null,
        'route' => null,
        'create' => null,
        'color' => null,
        'fields' => ['name'],
    ],

    'workflow_procedure_steps' => [
        'read' => 'workflow.config.read',
        'write' => 'workflow.config.write',
        'create_permission' => 'workflow.config.write',
        'route' => null,
        'create' => null,
        'color' => null,
        'fields' => ['name'],
    ],

    // Inventory module
    'inventory_products' => [
        'read' => 'inventory.read',
        'write' => 'inventory.write',
        'create_permission' => 'inventory.create',
        'route' => 'inventory.products.index',
        'create' => 'inventory.products.create',
        'color' => null,
        'fields' => ['name', 'internal_reference'],
    ],

    'inventory_locations' => [
        'read' => 'inventory.read',
        'write' => 'inventory.config',
        'create_permission' => 'inventory.config',
        'route' => 'inventory.config.locations.index',
        'create' => 'inventory.config.locations.create',
        'color' => null,
        'fields' => ['complete_name'],
    ],

    'inventory_warehouses' => [
        'read' => 'inventory.read',
        'write' => 'inventory.config',
        'create_permission' => 'inventory.config',
        'route' => 'inventory.config.warehouses.index',
        'create' => 'inventory.config.warehouses.create',
        'color' => null,
        'fields' => ['name', 'code'],
    ],

    'inventory_lots' => [
        'read' => 'inventory.read',
        'write' => 'inventory.write',
        'create_permission' => 'inventory.create',
        'route' => 'inventory.lots.index',
        'create' => 'inventory.lots.create',
        'color' => null,
        'fields' => ['name'],
    ],

    'inventory_uoms' => [
        'read' => 'inventory.read',
        'write' => 'inventory.config',
        'create_permission' => 'inventory.config',
        'route' => 'inventory.config.uoms.index',
        'create' => 'inventory.config.uoms.create',
        'color' => null,
        'fields' => ['name'],
    ],

    'inventory_uom_categories' => [
        'read' => 'inventory.read',
        'write' => 'inventory.config',
        'create_permission' => 'inventory.config',
        'route' => 'inventory.config.uoms.index',
        'create' => null,
        'color' => null,
        'fields' => ['name'],
    ],

    'inventory_operation_types' => [
        'read' => 'inventory.read',
        'write' => 'inventory.config',
        'create_permission' => 'inventory.config',
        'route' => 'inventory.config.operation-types.index',
        'create' => 'inventory.config.operation-types.create',
        'color' => null,
        'fields' => ['name'],
    ],

    'inventory_routes' => [
        'read' => 'inventory.read',
        'write' => 'inventory.config',
        'create_permission' => 'inventory.config',
        'route' => 'inventory.config.routes.index',
        'create' => 'inventory.config.routes.create',
        'color' => null,
        'fields' => ['name'],
    ],

    'inventory_product_categories' => [
        'read' => 'inventory.read',
        'write' => 'inventory.config',
        'create_permission' => 'inventory.config',
        'route' => 'inventory.config.product-categories.index',
        'create' => 'inventory.config.product-categories.create',
        'color' => null,
        'fields' => ['complete_name'],
    ],

    // Accounting module
    'accounts' => [
        'read' => 'accounting.read',
        'write' => 'accounting.write',
        'create_permission' => 'accounting.create',
        'route' => 'accounting.accounts.index',
        'create' => 'accounting.accounts.create',
        'color' => null,
        'fields' => ['code', 'name'],
    ],

    'account_journals' => [
        'read' => 'accounting.read',
        'write' => 'accounting.write',
        'create_permission' => 'accounting.create',
        'route' => 'accounting.journals.index',
        'create' => 'accounting.journals.create',
        'color' => null,
        'fields' => ['code', 'name'],
    ],

    'account_moves' => [
        'read' => 'accounting.read',
        'write' => 'accounting.write',
        'create_permission' => 'accounting.create',
        'route' => 'accounting.moves.index',
        'create' => 'accounting.moves.create',
        'color' => null,
        'fields' => ['name', 'ref'],
    ],

    'account_taxes' => [
        'read' => 'accounting.read',
        'write' => 'accounting.write',
        'create_permission' => 'accounting.create',
        'route' => 'accounting.taxes.index',
        'create' => 'accounting.taxes.create',
        'color' => null,
        'fields' => ['name'],
    ],

    'currency_rates' => [
        'read' => 'accounting.read',
        'write' => 'accounting.write',
        'create_permission' => 'accounting.write',
        'route' => 'accounting.currencies.index',
        'create' => 'accounting.currencies.create',
        'color' => null,
        'fields' => ['currency'],
    ],

    'accounting_payment_terms' => [
        'read' => 'accounting.read',
        'write' => 'accounting.write',
        'create_permission' => 'accounting.create',
        'route' => 'accounting.payment-terms.index',
        'create' => 'accounting.payment-terms.create',
        'color' => null,
        'fields' => ['name'],
        'active_only' => true,
    ],

    'accounting_incoterms' => [
        'read' => 'accounting.read',
        'write' => 'accounting.write',
        'create_permission' => 'accounting.create',
        'route' => 'accounting.incoterms.index',
        'create' => 'accounting.incoterms.create',
        'color' => null,
        'fields' => ['code', 'name'],
    ],

    'accounting_account_groups' => [
        'read' => 'accounting.read',
        'write' => 'accounting.write',
        'create_permission' => 'accounting.create',
        'route' => 'accounting.account-groups.index',
        'create' => 'accounting.account-groups.create',
        'color' => null,
        'fields' => ['name'],
    ],
];
