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
];
