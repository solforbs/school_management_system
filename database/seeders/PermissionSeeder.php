<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all permissions for each module
        $this->createPermissions();

        // Create roles and assign permissions
        $this->createRolesAndAssignPermissions();

        $this->command->info('Permissions and roles created successfully!');
    }

    /**
     * Create all permissions
     */
    private function createPermissions(): void
    {
        // Define modules and their permissions
        $modules = [
            'parent' => ['view', 'create', 'edit', 'update', 'delete'],
            'student' => ['view', 'create', 'edit', 'update', 'delete'],
            'teacher' => ['view', 'create', 'edit', 'update', 'delete'],
            'institution' => ['view', 'create', 'edit', 'update', 'delete'],
            'curriculum' => ['view', 'create', 'edit', 'update', 'delete'],
            'attendance' => ['view', 'create', 'edit', 'update', 'delete'],
            'examination' => ['view', 'create', 'edit', 'update', 'delete'],
            'feemanagement' => ['view', 'create', 'edit', 'update', 'delete'],
            'timetable' => ['view', 'create', 'edit', 'update', 'delete'],
            'classes' => ['view', 'create', 'edit', 'update', 'delete'],
            'lesson' => ['view', 'create', 'edit', 'update', 'delete', 'export'],
            'result' => ['view', 'create', 'edit', 'update', 'delete'],
            'subject' => ['view', 'create', 'edit', 'update', 'delete'],
            'reportcard' => ['view', 'edit'],
            'staff' => ['view', 'create', 'edit', 'update', 'delete'],
            'payroll' => ['view', 'create', 'edit', 'update', 'delete'],
            // What the school spends. The Accountant's to keep, the
            // Director's to read - the outgoing side of fee management.
            'expenditure' => ['view', 'create', 'edit', 'update', 'delete'],
            // Enrolling and revoking the offline sync clients. A Director's
            // call, not an Accountant's - a device inherits its account's
            // reach, so handing one out is a security decision.
            'syncdevice' => ['view', 'create', 'delete'],
            'user' => ['view', 'create', 'edit', 'update', 'delete'],
            'role' => ['view', 'create', 'edit', 'update', 'delete'],
            'permission' => ['view', 'create', 'edit', 'update', 'delete'],
            'setting' => ['view', 'create', 'edit', 'update', 'delete'],
            'dashboard' => ['view'],
            'report' => ['view', 'create', 'export'],
            'account' => ['view', 'create', 'edit', 'update', 'delete'],
            'finance' => ['view', 'create', 'edit', 'update', 'delete'],
            // 'create billing' covers initiating a subscription payment,
            // not full CRUD - there's nothing to edit/delete on a payment.
            'billing' => ['view', 'create'],
        ];

        // Create permissions for each module
        foreach ($modules as $module => $permissions) {
            foreach ($permissions as $permission) {
                Permission::firstOrCreate([
                    'name' => $permission.' '.$module,
                    'guard_name' => 'web',
                ]);
            }
        }
    }

    /**
     * Create roles and assign permissions.
     *
     * Role model:
     * - Admin: owns the application/platform itself. Full access to
     *   everything, including onboarding/removing schools (institutions) and
     *   managing users, roles, permissions and system settings.
     * - Director: represents a single school. Fully manages that school's
     *   day-to-day data (students, parents, teachers, curriculum,
     *   attendance, examinations, fees, timetable, finance/accounts,
     *   reports), but can only view/edit their own institution's profile —
     *   creating or deleting a school is an Admin-only, platform-level
     *   action.
     * - Accountant: finance-focused subset of a Director's access - staff
     *   payroll, fee management and the school's expenditure. They read the
     *   school's staff and students to bill and pay them, but manage
     *   neither.
     * - Parent/Student: read-only access scoped to their own records.
     */
    private function createRolesAndAssignPermissions(): void
    {
        // 1. Admin Role (System owner - full access to everything)
        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        // 2. Director Role (Manages a single school end-to-end)
        $director = Role::firstOrCreate(['name' => 'Director', 'guard_name' => 'web']);
        $directorPermissions = Permission::whereIn('name', [
            // Their school's profile: view/edit, plus creating it in the
            // first place (self-service onboarding). Note: institution
            // creation is still gated by ownership in the controller, not
            // this permission - a brand-new user has no role/permissions at
            // all until they self-create their first institution, so
            // gating creation behind a permission check would make
            // onboarding impossible. This grant exists for completeness of
            // the Director's documented capabilities, not as the actual
            // enforcement mechanism.
            'view institution',
            'create institution',
            'edit institution',
            'update institution',

            // Full CRUD over everything that makes up "their school"
            'view parent', 'create parent', 'edit parent', 'update parent', 'delete parent',
            'view student', 'create student', 'edit student', 'update student', 'delete student',
            'view teacher', 'create teacher', 'edit teacher', 'update teacher', 'delete teacher',
            'view curriculum', 'create curriculum', 'edit curriculum', 'update curriculum', 'delete curriculum',
            'view attendance', 'create attendance', 'edit attendance', 'update attendance', 'delete attendance',
            'view examination', 'create examination', 'edit examination', 'update examination', 'delete examination',
            'view feemanagement', 'create feemanagement', 'edit feemanagement', 'update feemanagement', 'delete feemanagement',
            'view timetable', 'create timetable', 'edit timetable', 'update timetable', 'delete timetable',
            'view classes', 'create classes', 'edit classes', 'update classes', 'delete classes',
            'view lesson', 'create lesson', 'edit lesson', 'update lesson', 'delete lesson', 'export lesson',
            'view result', 'create result', 'edit result', 'update result', 'delete result',
            'view subject', 'create subject', 'edit subject', 'update subject', 'delete subject',
            'view reportcard', 'edit reportcard',
            'view staff', 'create staff', 'edit staff', 'update staff', 'delete staff',
            'view payroll', 'create payroll', 'edit payroll', 'update payroll', 'delete payroll',
            'view expenditure', 'create expenditure', 'edit expenditure', 'update expenditure', 'delete expenditure',
            'view syncdevice', 'create syncdevice', 'delete syncdevice',
            'view account', 'create account', 'edit account', 'update account',
            'view finance', 'create finance', 'edit finance', 'update finance',

            // Subscription/billing - viewing plans and paying for them.
            'view billing', 'create billing',

            // Reporting and their own dashboard
            'view report', 'create report', 'export report',
            'view dashboard',
        ])->get();
        $director->syncPermissions($directorPermissions);

        // 3. Accountant Role (Payroll and fee management)
        $accountant = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'web']);
        $accountantPermissions = Permission::whereIn('name', [
            'view feemanagement',
            'create feemanagement',
            'edit feemanagement',
            'update feemanagement',
            'delete feemanagement',

            // Payroll is theirs end-to-end, but the staff records it pays
            // against belong to the Director - an Accountant can only read
            // them, never hire, edit or remove anyone.
            'view staff',
            'view payroll',
            'create payroll',
            'edit payroll',
            'update payroll',
            'delete payroll',

            // Spending is the Accountant's book to keep end-to-end,
            // including the headings it's filed under.
            'view expenditure',
            'create expenditure',
            'edit expenditure',
            'update expenditure',
            'delete expenditure',

            'view student',
            'view parent',
            'view account',
            'create account',
            'edit account',
            'update account',
            'view finance',
            'create finance',
            'edit finance',
            'update finance',
            'view billing',
            'create billing',
            'view report',
            'create report',
            'export report',
            'view dashboard',
        ])->get();
        $accountant->syncPermissions($accountantPermissions);

        // 4. Parent Role (Limited access - view their children's information)
        $parent = Role::firstOrCreate(['name' => 'Parent', 'guard_name' => 'web']);
        $parentPermissions = Permission::whereIn('name', [
            'view student',
            'view attendance',
            'view examination',
            'view timetable',
            'view classes',
            'view feemanagement',
            'view lesson',
            'view result',
            'view reportcard',
            'view dashboard',
            'view report',
        ])->get();
        $parent->syncPermissions($parentPermissions);

        // 5. Student Role (Very limited access)
        $student = Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'web']);
        $studentPermissions = Permission::whereIn('name', [
            'view attendance',
            'view examination',
            'view timetable',
            'view classes',
            'view lesson',
            'view result',
            'view reportcard',
            'view dashboard',
            'view report',
        ])->get();
        $student->syncPermissions($studentPermissions);

        // 6. Teacher Role (Views their school's academic data; can only
        // record results for the subjects they've been assigned in a class
        // - or, if they're its class teacher, for every subject in their
        // own class. No lesson-attendance or report-card access - those are
        // Director/Parent/Student concerns - and no curriculum access,
        // which is Director-only.
        $teacher = Role::firstOrCreate(['name' => 'Teacher', 'guard_name' => 'web']);
        $teacherPermissions = Permission::whereIn('name', [
            'view student',
            'view attendance',
            'view examination',
            'view timetable',
            'view classes',
            'view result', 'create result', 'edit result', 'update result',
            'view subject',
            'view dashboard',
            'view report',
        ])->get();
        $teacher->syncPermissions($teacherPermissions);
    }
}
