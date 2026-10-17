<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    protected array $permissions = [
        'stands.view',
        'stands.reset',
        'movements.view',
        'movements.create',
        'movements.update',
        'movements.delete',
        'movements.override',
        'reports.view',
        'reports.export',
        'history.view',
        'audit.view',
        'master-data.manage',
        'users.manage',
        'notifications.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'admin' => $this->permissions,
            'supervisor' => [
                'stands.view', 'stands.reset',
                'movements.view', 'movements.create', 'movements.update', 'movements.delete', 'movements.override',
                'reports.view', 'reports.export', 'history.view', 'audit.view', 'notifications.view',
            ],
            'amc_operator' => [
                'stands.view',
                'movements.view', 'movements.create', 'movements.update',
                'reports.view', 'reports.export', 'history.view', 'notifications.view',
            ],
            'atc_viewer' => [
                'stands.view', 'movements.view', 'history.view', 'reports.view', 'notifications.view',
            ],
        ];

        foreach ($roles as $name => $permissions) {
            $role = Role::findOrCreate($name, 'web');
            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
