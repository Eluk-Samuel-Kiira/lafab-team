<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Str;

class SyncPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:sync
                            {--group= : Sync permissions for a specific module/group}
                            {--dry-run : Show what would be added without actually adding}
                            {--force : Force sync even if permissions exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync permissions - adds new permissions that don\'t already exist';

    /**
     * Define all permissions grouped by modules
     */
    protected function getPermissions(): array
    {
        return [
            // ============================================================
            // USER MANAGEMENT
            // ============================================================
            'user_management' => [
                'view users',
                'create users',
                'edit users',
                'delete users',
                'assign roles',
                'activate users',
                'deactivate users',
                'reset user passwords',
            ],

            // ============================================================
            // ROLE & PERMISSION MANAGEMENT
            // ============================================================
            'role_permission' => [
                'view roles',
                'create roles',
                'edit roles',
                'delete roles',
                'view permissions',
                'create permissions',
                'edit permissions',
                'delete permissions',
                'assign permissions',
                'revoke permissions',
            ],

            // ============================================================
            // JOB MANAGEMENT
            // ============================================================
            'job_management' => [
                'view jobs',
                'create jobs',
                'edit jobs',
                'delete jobs',
                'publish jobs',
                'archive jobs',
                'feature jobs',
            ],

            // ============================================================
            // EMPLOYEE MANAGEMENT
            // ============================================================
            'employee_management' => [
                'view employees',
                'create employees',
                'edit employees',
                'delete employees',
                'manage employee salaries',
                'view employee reports',
            ],

            // ============================================================
            // EXPENSE MANAGEMENT
            // ============================================================
            'expense_management' => [
                'view expenses',
                'create expenses',
                'edit expenses',
                'delete expenses',
                'approve expenses',
                'pay expenses',
                'cancel expenses',
                'reject expenses',
                'view expense reports',
            ],

            // ============================================================
            // PAYMENT MANAGEMENT
            // ============================================================
            'payment_management' => [
                'view payments',
                'create payments',
                'edit payments',
                'delete payments',
                'process payments',
                'approve payments',
                'cancel payments',
                'view payment reports',
            ],

            // ============================================================
            // COMPENSATION
            // ============================================================
            'compensation' => [
                'view compensation',
                'manage salary structures',
                'process payroll',
                'view payroll reports',
                'manage bonuses',
                'manage phantom equity',
                'view compensation reports',
            ],

            // ============================================================
            // DEPARTMENT MANAGEMENT
            // ============================================================
            'department_management' => [
                'view departments',
                'create departments',
                'edit departments',
                'delete departments',
                'manage department budgets',
            ],

            // ============================================================
            // ACCOUNTING / FINANCE
            // ============================================================
            'accounting' => [
                'view accounting',
                'manage payment methods',
                'view account balances',
                'view transaction ledger',
                'view income statement',
                'view cash flow',
                'view financial reports',
                'manage currencies',
            ],

            // ============================================================
            // REPORTS
            // ============================================================
            'reports' => [
                'view reports',
                'view expense reports',
                'view financial reports',
                'export reports',
                'schedule reports',
            ],

            // ============================================================
            // SYSTEM SETTINGS
            // ============================================================
            'system_settings' => [
                'view settings',
                'edit settings',
                'manage system configuration',
                'view audit logs',
                'clear cache',
            ],

            // ============================================================
            // DASHBOARD
            // ============================================================
            'dashboard' => [
                'view dashboard',
                'view statistics',
                'view analytics',
            ],

            // ============================================================
            // TENANT MANAGEMENT (if multi-tenant)
            // ============================================================
            'tenant_management' => [
                'view tenants',
                'create tenants',
                'edit tenants',
                'delete tenants',
                'manage tenant users',
            ],
        ];
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = $this->getPermissions();
        $group = $this->option('group');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Filter by group if specified
        if ($group) {
            if (!isset($permissions[$group])) {
                $this->error("Group '{$group}' not found. Available groups:");
                foreach (array_keys($permissions) as $key) {
                    $this->line("  - {$key}");
                }
                return 1;
            }
            $permissions = [$group => $permissions[$group]];
        }

        $totalAdded = 0;
        $totalSkipped = 0;
        $totalPermissions = 0;

        $this->info('🔄 Syncing permissions...');
        $this->newLine();

        foreach ($permissions as $groupName => $permList) {
            $this->info("📁 Group: " . ucfirst(str_replace('_', ' ', $groupName)));
            $this->line(str_repeat('-', 50));

            foreach ($permList as $permissionName) {
                $totalPermissions++;
                
                // Check if permission already exists
                $exists = Permission::where('name', $permissionName)->exists();

                if ($exists && !$force) {
                    $this->line("  ⏭️  [SKIPPED] {$permissionName} (already exists)");
                    $totalSkipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("  📝 [DRY RUN] Would add: {$permissionName}");
                    if ($exists) {
                        $this->line("     (would force re-add)");
                    }
                    $totalAdded++;
                    continue;
                }

                try {
                    if ($exists && $force) {
                        // Force re-add - update existing
                        $permission = Permission::where('name', $permissionName)->first();
                        $permission->touch();
                        $this->line("  🔄 [UPDATED] {$permissionName}");
                    } else {
                        // Create new permission
                        Permission::create([
                            'name' => $permissionName,
                            'guard_name' => 'web'
                        ]);
                        $this->line("  ✅ [ADDED] {$permissionName}");
                    }
                    $totalAdded++;
                } catch (\Exception $e) {
                    $this->error("  ❌ [ERROR] Failed to add {$permissionName}: " . $e->getMessage());
                    $totalSkipped++;
                }
            }

            $this->newLine();
        }

        // Reset cached permissions again
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Summary
        $this->line(str_repeat('=', 50));
        $this->newLine();

        if ($dryRun) {
            $this->info('📊 DRY RUN SUMMARY:');
            $this->line("  Total permissions: {$totalPermissions}");
            $this->line("  Would add: {$totalAdded}");
            $this->line("  Would skip: {$totalSkipped}");
            $this->newLine();
            $this->info('Run without --dry-run to actually add these permissions.');
        } else {
            $this->info('📊 SYNC SUMMARY:');
            $this->line("  Total permissions: {$totalPermissions}");
            $this->line("  ✅ Added: {$totalAdded}");
            $this->line("  ⏭️  Skipped: {$totalSkipped}");
            $this->newLine();
            
            if ($totalAdded > 0) {
                $this->info('✅ Permissions synced successfully!');
            } else {
                $this->info('ℹ️  No new permissions to add.');
            }
        }

        return 0;
    }

    /**
     * Get the list of permissions (alias for backward compatibility)
     */
    public function getPermissionsList(): array
    {
        return $this->getPermissions();
    }
}