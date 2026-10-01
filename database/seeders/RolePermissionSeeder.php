<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'dashboard.view' => 'View the staff/admin dashboard',
            'accounts.create' => 'Create patron accounts',
            'accounts.verify-payment' => 'Verify student payment status',
            'accounts.bulk-import' => 'Bulk-create accounts from a roll number list',
            'accounts.manage' => 'Edit or deactivate any account',
            'roles.assign' => 'Assign roles to staff',
            'roles.manage' => 'Create or edit roles and permissions',
            'tasks.assign' => 'Delegate tasks to other staff',
            'tasks.view-own' => 'View tasks assigned to yourself',
            'catalog.view' => 'View catalog records',
            'catalog.create' => 'Add new catalog records',
            'catalog.edit' => 'Edit catalog records',
            'catalog.delete' => 'Delete catalog records',
            'circulation.view' => 'View borrowing/return activity',
            'circulation.resolve-dispute' => 'Resolve lending disputes',
            'fines.manage' => 'Manage patron fines',
        ];

        $permissionModels = collect($permissions)->mapWithKeys(function ($description, $name) {
            return [$name => Permission::firstOrCreate(['name' => $name], ['description' => $description])];
        });

        $roles = [
            'Admin' => array_keys($permissions), // everything
            'Sub-Controller' => ['accounts.create', 'accounts.verify-payment', 'accounts.bulk-import'],
            'Librarian' => ['dashboard.view', 'accounts.manage', 'roles.assign', 'tasks.assign', 'catalog.view'],
            'Assistant Librarian' => ['dashboard.view', 'tasks.view-own', 'catalog.view'],
            'Senior Cataloger' => ['catalog.view', 'catalog.create', 'catalog.edit', 'catalog.delete', 'tasks.assign'],
            'Cataloger' => ['catalog.view', 'catalog.create', 'catalog.edit', 'tasks.view-own'],
            'Attendant' => ['tasks.view-own'],
            'Circulation Staff' => ['circulation.view', 'circulation.resolve-dispute', 'fines.manage'],
        ];

        foreach ($roles as $roleName => $permissionNames) {
            $role = Role::firstOrCreate(['name' => $roleName]);

            $role->permissions()->sync(
                collect($permissionNames)->map(fn ($name) => $permissionModels[$name]->id)
            );
        }
    }
}