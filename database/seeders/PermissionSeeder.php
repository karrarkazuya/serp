<?php

namespace Database\Seeders;

use App\Models\Security\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Contacts
            ['name' => 'Read Contacts',   'key' => 'contacts.read',   'module' => 'contacts',  'description' => 'View the contacts list and individual contact records.'],
            ['name' => 'Create Contacts', 'key' => 'contacts.create', 'module' => 'contacts',  'description' => 'Create new contact records.'],
            ['name' => 'Edit Contacts',   'key' => 'contacts.write',  'module' => 'contacts',  'description' => 'Edit and archive contact records.'],
            ['name' => 'Delete Contacts', 'key' => 'contacts.unlink', 'module' => 'contacts',  'description' => 'Permanently delete contact records.'],

            // Users
            ['name' => 'Read Users',      'key' => 'users.read',      'module' => 'users',     'description' => 'View user list and profiles.'],
            ['name' => 'Create Users',    'key' => 'users.create',    'module' => 'users',     'description' => 'Create new user accounts.'],
            ['name' => 'Edit Users',      'key' => 'users.write',     'module' => 'users',     'description' => 'Edit user accounts and assign roles.'],
            ['name' => 'Delete Users',    'key' => 'users.unlink',    'module' => 'users',     'description' => 'Delete user accounts.'],

            // Roles
            ['name' => 'Read Roles',      'key' => 'roles.read',      'module' => 'roles',     'description' => 'View roles and their permissions.'],
            ['name' => 'Create Roles',    'key' => 'roles.create',    'module' => 'roles',     'description' => 'Create new roles.'],
            ['name' => 'Edit Roles',      'key' => 'roles.write',     'module' => 'roles',     'description' => 'Edit roles and assign permissions.'],
            ['name' => 'Delete Roles',    'key' => 'roles.unlink',    'module' => 'roles',     'description' => 'Delete roles.'],

            // Companies
            ['name' => 'Read Companies',   'key' => 'companies.read',   'module' => 'companies', 'description' => 'View company list and company records.'],
            ['name' => 'Create Companies', 'key' => 'companies.create', 'module' => 'companies', 'description' => 'Create new company records.'],
            ['name' => 'Edit Companies',   'key' => 'companies.write',  'module' => 'companies', 'description' => 'Edit and archive company records.'],
            ['name' => 'Delete Companies', 'key' => 'companies.unlink', 'module' => 'companies', 'description' => 'Permanently delete company records.'],

            // Settings
            ['name' => 'Read Settings',   'key' => 'settings.read',   'module' => 'settings',  'description' => 'View application settings.'],
            ['name' => 'Edit Settings',   'key' => 'settings.write',  'module' => 'settings',  'description' => 'Modify application settings.'],

            // Workflow — Tickets
            ['name' => 'Read Tickets',           'key' => 'workflow.tickets.read',    'module' => 'workflow', 'description' => 'View tickets assigned or visible to the user.'],
            ['name' => 'Create Tickets',         'key' => 'workflow.tickets.create',  'module' => 'workflow', 'description' => 'Create new tickets from templates.'],
            ['name' => 'Edit Tickets',           'key' => 'workflow.tickets.write',   'module' => 'workflow', 'description' => 'Update ticket state, assignment, and inputs.'],
            ['name' => 'Delete Tickets',         'key' => 'workflow.tickets.unlink',  'module' => 'workflow', 'description' => 'Delete ticket records.'],

            // Workflow — Procedures
            ['name' => 'Read Procedures',        'key' => 'workflow.procedures.read',   'module' => 'workflow', 'description' => 'View procedures and their tasks.'],
            ['name' => 'Create Procedures',      'key' => 'workflow.procedures.create', 'module' => 'workflow', 'description' => 'Start procedures from templates.'],
            ['name' => 'Edit Procedures',        'key' => 'workflow.procedures.write',  'module' => 'workflow', 'description' => 'Complete, close, and manage procedure tasks.'],
            ['name' => 'Delete Procedures',      'key' => 'workflow.procedures.unlink', 'module' => 'workflow', 'description' => 'Delete procedure records.'],

            // Workflow — Configuration (admin-level)
            ['name' => 'Read Workflow Config',   'key' => 'workflow.config.read',   'module' => 'workflow', 'description' => 'View workflow configuration (groups, departments, templates).'],
            ['name' => 'Edit Workflow Config',   'key' => 'workflow.config.write',  'module' => 'workflow', 'description' => 'Create and edit workflow configuration.'],
            ['name' => 'Delete Workflow Config', 'key' => 'workflow.config.unlink', 'module' => 'workflow', 'description' => 'Delete workflow configuration records.'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(['key' => $perm['key']], $perm);
        }
    }
}
