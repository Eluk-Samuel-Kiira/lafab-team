<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define roles and their permissions
        $roles = [
            'super_admin' => [
                'description' => 'Full system access with all permissions',
                'permissions' => Permission::all()->pluck('name')->toArray() // All permissions
            ],
            'admin' => [
                'description' => 'Administrative access without system settings',
                'permissions' => [
                    'view users', 'create users', 'edit users', 'delete users',
                    'view roles', 'create roles', 'edit roles',
                    'view jobs', 'create jobs', 'edit jobs', 'delete jobs', 'approve jobs', 'publish jobs',
                    'view applications', 'review applications', 'shortlist candidates', 'reject applications', 'schedule interviews',
                    'view candidates', 'create candidates', 'edit candidates', 'delete candidates',
                    'view clients', 'create clients', 'edit clients', 'delete clients',
                    'view dashboard', 'view reports', 'export reports', 'view analytics',
                    'view tickets', 'respond tickets',
                ]
            ],
            'supervisor' => [
                'description' => 'Supervisory access to monitor and manage team activities',
                'permissions' => [
                    'view users',
                    'view jobs', 'create jobs', 'edit jobs', 'approve jobs', 'publish jobs',
                    'view applications', 'review applications', 'shortlist candidates', 'reject applications', 'schedule interviews',
                    'view candidates', 'create candidates', 'edit candidates',
                    'view clients', 'create clients', 'edit clients',
                    'view dashboard', 'view reports',
                    'view tickets', 'respond tickets',
                ]
            ],
            'moderator' => [
                'description' => 'Content moderation and basic management',
                'permissions' => [
                    'view jobs', 'edit jobs', 'archive jobs',
                    'view applications', 'review applications', 'shortlist candidates', 'reject applications',
                    'view candidates', 'edit candidates',
                    'view clients',
                    'view dashboard',
                    'view tickets',
                ]
            ],
            'job_poster' => [
                'description' => 'Can post and manage their own jobs only',
                'permissions' => [
                    'view jobs', 'create jobs', 'edit jobs', 'delete jobs',
                    'view applications', 'review applications',
                    'view candidates',
                    'view dashboard',
                ]
            ],
        ];

        foreach ($roles as $roleName => $roleData) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web'
            ]);
            
            // Assign permissions to role
            if (!empty($roleData['permissions'])) {
                $role->syncPermissions($roleData['permissions']);
            }
            
            $this->command->info("Role '{$roleName}' created/updated with " . count($roleData['permissions']) . " permissions");
        }

        $this->command->info('Roles seeded successfully!');
    }
}