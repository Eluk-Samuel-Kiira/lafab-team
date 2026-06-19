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
            // ============================================================
            // USER MANAGEMENT
            // ============================================================
            'view users',
            'create users',
            'edit users',
            'delete users',
            'assign roles',
            'activate users',
            'deactivate users',
            'reset user passwords',

            // ============================================================
            // ROLE & PERMISSION MANAGEMENT
            // ============================================================
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
            'view permissions',
            'assign permissions',

            // ============================================================
            // JOB MANAGEMENT
            // ============================================================
            'view jobs',
            'create jobs',
            'edit jobs',
            'delete jobs',
            'approve jobs',
            'publish jobs',
            'archive jobs',
            'feature jobs',
            'mark jobs urgent',

            // ============================================================
            // JOB APPLICATIONS
            // ============================================================
            'view applications',
            'review applications',
            'shortlist candidates',
            'reject applications',
            'schedule interviews',
            'conduct interviews',
            'rate candidates',
            'send interview feedback',

            // ============================================================
            // CANDIDATES
            // ============================================================
            'view candidates',
            'create candidates',
            'edit candidates',
            'delete candidates',
            'export candidates',
            'import candidates',
            'view talent pool',
            'add to talent pool',
            'search candidates',
            'contact candidates',
            'track candidate status',

            // ============================================================
            // CLIENTS / COMPANIES
            // ============================================================
            'view clients',
            'create clients',
            'edit clients',
            'delete clients',
            'manage client contracts',
            'view client history',
            'deactivate clients',

            // ============================================================
            // RECRUITMENT
            // ============================================================
            'manage job postings',
            'manage interviews',
            'send offers',
            'manage onboarding',
            'view onboarding tasks',
            'complete onboarding tasks',
            'manage offboarding',
            'view offboarding tasks',

            // ============================================================
            // FINANCE: REVENUE
            // ============================================================
            'view revenue',
            'create revenue',
            'edit revenue',
            'delete revenue',
            'approve revenue',
            'reconcile revenue',
            'export revenue reports',
            'view revenue dashboard',
            'generate revenue forecasts',

            // ============================================================
            // FINANCE: EXPENSES
            // ============================================================
            'view expenses',
            'create expenses',
            'edit expenses',
            'delete expenses',
            'approve expenses',
            'pay expenses',
            'reject expenses',
            'cancel expenses',
            'reconcile expenses',
            'export expense reports',
            'view expense dashboard',
            'categorize expenses',

            // ============================================================
            // FINANCE: DEPOSITS
            // ============================================================
            'view deposits',
            'create deposits',
            'edit deposits',
            'delete deposits',
            'approve deposits',
            'reconcile deposits',
            'track deposits',
            'cancel deposits',
            
            // ============================================================
            // EXPENSE: EXPENSE CATEGORY
            // ============================================================
            'view expense categories',
            'create expense categories',
            'edit expense categories',
            'delete expense categories',
            'approve expense categories',

            // ============================================================
            // FINANCE: FINANCIAL REPORTS
            // ============================================================
            'view financial reports',
            'export financial reports',
            'view balance sheet',
            'view profit & loss',
            'view cash flow',
            'generate custom reports',
            'view trial balance',
            'view general ledger',

            // ============================================================
            // FINANCE: SALARY & PAYROLL
            // ============================================================
            'view salaries',
            'create salary',
            'edit salary',
            'delete salary',
            'approve salary',
            'process payroll',
            'view payroll',
            'export payroll reports',
            'view salary structure',
            'create salary structure',
            'edit salary structure',
            'delete salary structure',

            // ============================================================
            // FINANCE: SALARY PAYMENTS
            // ============================================================
            'view salary payments',
            'create salary payments',
            'edit salary payments',
            'delete salary payments',
            'approve salary payments',
            'process salary payments',
            'export salary payment reports',
            'reconcile salary payments',
            'cancel salary payments',
            'reject salary payments',

            // ============================================================
            // FINANCE: BONUS
            // ============================================================
            'view bonuses',
            'create bonuses',
            'edit bonuses',
            'delete bonuses',
            'approve bonuses',
            'pay bonuses',
            'calculate bonuses',
            'export bonus reports',
            'manage bonus schemes',

            // ============================================================
            // FINANCE: PHANTOM EQUITY
            // ============================================================
            'view phantom equity',
            'create phantom equity',
            'edit phantom equity',
            'delete phantom equity',
            'approve phantom equity',
            'vest phantom equity',
            'calculate phantom equity',
            'export phantom equity reports',
            'manage phantom equity schemes',

            // ============================================================
            // FINANCE: PROFIT SHARE
            // ============================================================
            'view profit share',
            'create profit share',
            'edit profit share',
            'delete profit share',
            'approve profit share',
            'pay profit share',
            'calculate profit share',
            'distribute profit share',
            'export profit share reports',
            'manage profit share schemes',

            // ============================================================
            // FINANCE: PROFIT SHARE PERIODS
            // ============================================================
            'view profit share periods',
            'create profit share periods',
            'edit profit share periods',
            'delete profit share periods',
            'open profit share period',
            'close profit share period',
            'review profit share period',

            // ============================================================
            // FINANCE: CURRENCY
            // ============================================================
            'view currencies',
            'create currencies',
            'edit currencies',
            'delete currencies',
            'set default currency',
            'manage exchange rates',
            'update exchange rates',

            // ============================================================
            // FINANCE: PAYMENT METHODS
            // ============================================================
            'view payment methods',
            'create payment methods',
            'edit payment methods',
            'delete payment methods',
            'activate payment methods',
            'deactivate payment methods',
            'assign payment methods',

            // ============================================================
            // FINANCE: PAYMENT SOURCE
            // ============================================================
            'view payment sources',
            'create payment sources',
            'edit payment sources',
            'delete payment sources',
            'activate payment sources',
            'deactivate payment sources',
            'track payment sources',

            // ============================================================
            // FINANCE: PAYMENT PURPOSE
            // ============================================================
            'view payment purposes',
            'create payment purposes',
            'edit payment purposes',
            'delete payment purposes',
            'activate payment purposes',
            'deactivate payment purposes',

            // ============================================================
            // HR: EMPLOYEES
            // ============================================================
            'view employees',
            'create employees',
            'edit employees',
            'delete employees',
            'view employee details',
            'edit employee details',
            'activate employees',
            'deactivate employees',
            'export employee list',
            'import employees',
            'manage employee documents',
            'view employee contracts',
            'create employee contracts',
            'edit employee contracts',
            'terminate employee contracts',

            // ============================================================
            // HR: DEPARTMENTS
            // ============================================================
            'view departments',
            'create departments',
            'edit departments',
            'delete departments',
            'view department structure',
            'manage department heads',

            // ============================================================
            // HR: PERFORMANCE REVIEWS
            // ============================================================
            'view performance reviews',
            'create performance reviews',
            'edit performance reviews',
            'delete performance reviews',
            'submit performance reviews',
            'approve performance reviews',
            'schedule performance reviews',
            'view performance metrics',
            'export performance reports',
            'set performance goals',
            'track performance goals',
            'manage performance cycles',

            // ============================================================
            // HR: TIME & ATTENDANCE
            // ============================================================
            'view attendance',
            'create attendance',
            'edit attendance',
            'delete attendance',
            'approve attendance',
            'export attendance reports',
            'view timesheets',
            'approve timesheets',
            'manage leave requests',
            'approve leave requests',

            // ============================================================
            // HR: RECRUITMENT
            // ============================================================
            'manage recruitment',
            'post job openings',
            'view applicants',
            'shortlist applicants',
            'schedule interviews',
            'conduct interviews',
            'rate applicants',
            'send offers',
            'manage onboarding',
            'manage offboarding',

            // ============================================================
            // SYSTEM SETTINGS
            // ============================================================
            'manage settings',
            'view system logs',
            'manage email templates',
            'manage notifications',
            'manage audit logs',
            'clear cache',
            'manage integrations',

            // ============================================================
            // DASHBOARD & REPORTS
            // ============================================================
            'view dashboard',
            'view reports',
            'export reports',
            'view analytics',
            'view HR dashboard',
            'view finance dashboard',
            'view operations dashboard',
            'view sales dashboard',

            // ============================================================
            // SUPPORT & TICKETS
            // ============================================================
            'view tickets',
            'respond tickets',
            'manage tickets',
            'close tickets',
            'escalate tickets',
            'assign tickets',

            // ============================================================
            // AUDIT
            // ============================================================
            'view audit trails',
            'export audit reports',
            'manage audit logs',
            'view system security',

            // ============================================================
            // NOTIFICATIONS
            // ============================================================
            'view notifications',
            'send notifications',
            'manage notification templates',
            'configure notifications',

            // ============================================================
            // EXPORT MANAGEMENT
            // ============================================================
            'export all reports',
            'export financial data',
            'export HR data',
            'export operational data',
            'schedule exports',
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