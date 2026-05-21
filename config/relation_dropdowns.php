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
];
