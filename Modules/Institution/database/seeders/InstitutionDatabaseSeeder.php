<?php

namespace Modules\Institution\Database\Seeders;

use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Institution\Models\Institution;
use Modules\Parent\Models\ParentDetails;
use Modules\Staff\Models\StaffDetails;
use Modules\Student\Models\StudentDetails;
use Modules\Teacher\Models\TeacherDetails;

class InstitutionDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            PlanSeeder::class,
        ]);

        $plan = Plan::where('slug', 'premium')->first() ?? Plan::first();

        // 1. Director (School Owner)
        $director = User::firstOrCreate(
            ['email' => 'director@greenvalley.ac.ke'],
            [
                'name' => 'Green Valley Director',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );

        if (! $director->hasRole('Director')) {
            $director->assignRole('Director');
        }

        // 2. Green Valley Academy Institution
        $institution = Institution::updateOrCreate(
            ['code' => 'GVA001'],
            [
                'user_id' => $director->id,
                'name' => 'Green Valley Academy',
                'type' => 'Primary & Secondary School',
                'email' => 'info@greenvalley.ac.ke',
                'phone' => '+254 700 000 000',
                'alternate_phone' => '+254 711 000 000',
                'website' => 'https://greenvalley.ac.ke',
                'country' => 'Kenya',
                'county' => 'Nairobi',
                'city' => 'Nairobi',
                'postal_address' => 'P.O. Box 12345-00100',
                'physical_address' => 'Green Valley Way, Karen, Nairobi',
                'principal_name' => 'Dr. Jane Mwangi',
                'principal_phone' => '+254 722 000 000',
                'education_level' => 'Primary & Secondary',
                'min_electives' => 1,
                'max_electives' => 4,
                'timezone' => 'Africa/Nairobi',
                'is_active' => true,
                'status' => 'active',
                'is_approved' => false,
                'approved_at' => null,
                'subscription_plan' => $plan?->id,
                'subscription_started_at' => now(),
                'subscription_expires_at' => now()->addYear(),
            ]
        );

        $director->update(['active_institution_id' => $institution->id]);

        // 3. Accountant Account & Staff Details
        $accountant = User::firstOrCreate(
            ['email' => 'accountant@greenvalley.ac.ke'],
            [
                'name' => 'Green Valley Accountant',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );

        if (! $accountant->hasRole('Accountant')) {
            $accountant->assignRole('Accountant');
        }

        StaffDetails::firstOrCreate(
            ['user_id' => $accountant->id],
            [
                'institution_id' => $institution->id,
                'name' => 'Green Valley Accountant',
                'email' => 'accountant@greenvalley.ac.ke',
                'phone' => '+254 733 000 001',
                'job_title' => 'Chief Accountant',
                'department' => 'Finance',
                'employment_type' => 'full_time',
                'is_active' => true,
                'status' => 'active',
            ]
        );

        // 4. Teacher Account & Teacher Details
        $teacher = User::firstOrCreate(
            ['email' => 'teacher@greenvalley.ac.ke'],
            [
                'name' => 'Green Valley Teacher',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );

        if (! $teacher->hasRole('Teacher')) {
            $teacher->assignRole('Teacher');
        }

        TeacherDetails::firstOrCreate(
            ['teacher_id' => $teacher->id],
            [
                'institution_id' => $institution->id,
                'phone' => '+254 733 000 002',
                'employee_number' => 'TCH-001',
                'department' => 'Sciences',
                'qualification' => 'Bachelor of Education',
                'hire_date' => now()->subYears(2),
                'is_active' => true,
                'status' => 'active',
            ]
        );

        // 5. Parent Account & Parent Details
        $parent = User::firstOrCreate(
            ['email' => 'parent@greenvalley.ac.ke'],
            [
                'name' => 'Green Valley Parent',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );

        if (! $parent->hasRole('Parent')) {
            $parent->assignRole('Parent');
        }

        ParentDetails::firstOrCreate(
            ['parent_id' => $parent->id],
            [
                'parent_phone' => '+254 733 000 003',
                'parent_occupation' => 'Engineer',
            ]
        );

        // 6. Student Account & Student Details
        $student = User::firstOrCreate(
            ['email' => 'student@greenvalley.ac.ke'],
            [
                'name' => 'Green Valley Student',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );

        if (! $student->hasRole('Student')) {
            $student->assignRole('Student');
        }

        StudentDetails::firstOrCreate(
            ['student_id' => $student->id],
            [
                'institution_id' => $institution->id,
                'parent_id' => $parent->id,
                'guardian_name' => 'Green Valley Parent',
                'guardian_email' => 'parent@greenvalley.ac.ke',
                'guardian_phone' => '+254 733 000 003',
                'admission_number' => 'GVA/2026/001',
                'student_number' => 'STD-001',
                'gender' => 'female',
                'is_active' => true,
                'enrollment_status' => 'active',
            ]
        );
    }
}
