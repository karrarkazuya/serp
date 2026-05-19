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

    'workflow_groups' => [
        'read' => 'workflow.config.read',
        'write' => 'workflow.config.write',
        'create_permission' => 'workflow.config.write',
        'route' => 'workflow.config.groups.index',
        'create' => 'workflow.config.groups.create',
        'color' => null,
        'fields' => ['name'],
    ],

    'workflow_departments' => [
        'read' => 'workflow.config.read',
        'write' => 'workflow.config.write',
        'create_permission' => 'workflow.config.write',
        'route' => 'workflow.config.departments.index',
        'create' => 'workflow.config.departments.create',
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
];
