<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Str;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all permissions grouped by modules
        $permissions = [
            // User Management
            'view users',
            'create users',
            'edit users',
            'delete users',
            'assign roles',
            
            // Role Management
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
            
            // Permission Management
            'view permissions',
            'assign permissions',
            
            // Job Management
            'view jobs',
            'create jobs',
            'edit jobs',
            'delete jobs',
            'approve jobs',
            'publish jobs',
            'archive jobs',
            
            // Job Applications
            'view applications',
            'review applications',
            'shortlist candidates',
            'reject applications',
            'schedule interviews',
            
            // Candidates
            'view candidates',
            'create candidates',
            'edit candidates',
            'delete candidates',
            'export candidates',
            'import candidates',
            
            // Clients/Companies
            'view clients',
            'create clients',
            'edit clients',
            'delete clients',
            'manage client contracts',
            
            // Dashboard & Reports
            'view dashboard',
            'view reports',
            'export reports',
            'view analytics',
            
            // System Settings
            'manage settings',
            'view system logs',
            'manage email templates',
            
            // Recruitment Specific
            'manage job postings',
            'manage interviews',
            'send offers',
            'manage onboarding',
            'view talent pool',
            'add to talent pool',
            
            // Support & Tickets
            'view tickets',
            'respond tickets',
            'manage tickets',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web'
            ]);
        }

        $this->command->info('Permissions seeded successfully! Total: ' . count($permissions));
    }
}