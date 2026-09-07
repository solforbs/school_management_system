<?php

namespace Modules\Institution\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Staff\Models\StaffDetails;
use Modules\Subject\Models\Subject;
use Modules\Teacher\Models\TeacherDetails;

class GreenValleyFacultyAndStaffSeeder extends Seeder
{
    private const TEACHER_FIRST_NAMES = [
        'John', 'Mary', 'David', 'Grace', 'Peter', 'Eunice', 'Joseph', 'Sarah', 'James', 'Lucy',
        'Daniel', 'Esther', 'Samuel', 'Faith', 'Paul', 'Mercy', 'Francis', 'Hannah', 'Stephen', 'Agnes',
        'Bernard', 'Beatrice', 'Charles', 'Catherine', 'Dennis', 'Dorothy', 'Edward', 'Elizabeth', 'George', 'Florence',
        'Henry', 'Gladys', 'Isaac', 'Helen', 'Jacob', 'Jane', 'Kenneth', 'Joyce', 'Lawrence', 'Judith',
    ];

    private const LAST_NAMES = [
        'Mwangi', 'Maina', 'Kamau', 'Kariuki', 'Njoroge', 'Kimani', 'Wanjiku', 'Otieno', 'Ochieng', 'Oduor',
        'Omondi', 'Anyango', 'Achieng', 'Kipchirchir', 'Kipkorir', 'Kipchumba', 'Chebet', 'Cherono', 'Mutua', 'Musyoka',
        'Wambua', 'Kilonzo', 'Wekesa', 'Wafula', 'Nekesa', 'Barasa', 'Juma', 'Nyongesa', 'Ouma', 'Okoth',
    ];

    private const DEPARTMENTS = ['Mathematics', 'Sciences', 'Humanities', 'Languages', 'Technical & Creative Arts', 'Primary Education'];

    public function run(): void
    {
        $institution = Institution::where('code', 'GVA001')->first();

        if (! $institution) {
            return;
        }

        $password = Hash::make('password');

        // 1. Seed 40 Teachers with hire_date & created_at spread over 3 years (36 months)
        $teacherUsers = [];

        for ($i = 1; $i <= 40; $i++) {
            $firstName = self::TEACHER_FIRST_NAMES[($i - 1) % count(self::TEACHER_FIRST_NAMES)];
            $lastName = self::LAST_NAMES[($i - 1) % count(self::LAST_NAMES)];
            $name = "{$firstName} {$lastName}";
            $email = "teacher{$i}@greenvalley.ac.ke";

            // Spread registration over 36 months (3 years)
            $monthsAgo = ($i * 9) % 36;
            $createdAt = now()->subMonths($monthsAgo)->subDays(rand(1, 28));

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                    'email_verified_at' => $createdAt,
                    'status' => 'active',
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );

            if (! $user->hasRole('Teacher')) {
                $user->assignRole('Teacher');
            }

            $teacherUsers[] = $user;

            $dept = self::DEPARTMENTS[($i - 1) % count(self::DEPARTMENTS)];

            TeacherDetails::firstOrCreate(
                ['teacher_id' => $user->id],
                [
                    'institution_id' => $institution->id,
                    'phone' => '+254 7'.rand(10000000, 99999999),
                    'employee_number' => 'TCH-'.(1000 + $i),
                    'department' => $dept,
                    'qualification' => 'Bachelor of Education',
                    'hire_date' => $createdAt->toDateString(),
                    'is_active' => true,
                    'status' => 'active',
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );
        }

        // Assign teachers to subjects & classes in subject_teachers table
        $classes = SchoolClass::where('institution_id', $institution->id)->get();
        $subjects = Subject::where('institution_id', $institution->id)->get();

        if ($classes->isNotEmpty() && $subjects->isNotEmpty()) {
            $subjectTeachersInsert = [];

            foreach ($classes as $classIndex => $class) {
                foreach ($subjects as $subjIndex => $subject) {
                    $assignedTeacher = $teacherUsers[($classIndex + $subjIndex) % count($teacherUsers)];

                    $subjectTeachersInsert[] = [
                        'institution_id' => $institution->id,
                        'class_id' => $class->id,
                        'subject_id' => $subject->id,
                        'teacher_id' => $assignedTeacher->id,
                        'assigned_by' => $institution->user_id,
                        'created_at' => now()->subMonths(12),
                        'updated_at' => now()->subMonths(12),
                    ];
                }
            }

            foreach (array_chunk($subjectTeachersInsert, 200) as $chunk) {
                DB::table('subject_teachers')->insertOrIgnore($chunk);
            }
        }

        // 2. Seed 15 Non-Teaching Staff & 36 Months Payroll History (Jan 2024 - Dec 2026)
        $rolesData = [
            ['title' => 'Senior Accountant', 'dept' => 'Finance', 'salary' => 85000],
            ['title' => 'Assistant Accountant', 'dept' => 'Finance', 'salary' => 55000],
            ['title' => 'Chief Librarian', 'dept' => 'Library', 'salary' => 60000],
            ['title' => 'IT Systems Administrator', 'dept' => 'ICT', 'salary' => 75000],
            ['title' => 'School Nurse', 'dept' => 'Health', 'salary' => 65000],
            ['title' => 'Head Security Officer', 'dept' => 'Security', 'salary' => 45000],
            ['title' => 'Senior Driver', 'dept' => 'Transport', 'salary' => 40000],
            ['title' => 'Science Lab Technician', 'dept' => 'Laboratory', 'salary' => 50000],
            ['title' => 'Computer Lab Technician', 'dept' => 'ICT', 'salary' => 50000],
            ['title' => 'Executive Secretary', 'dept' => 'Administration', 'salary' => 55000],
            ['title' => 'Stores & Inventory Manager', 'dept' => 'Procurement', 'salary' => 60000],
            ['title' => 'Grounds Supervisor', 'dept' => 'Operations', 'salary' => 38000],
            ['title' => 'Catering Manager', 'dept' => 'Catering', 'salary' => 52000],
            ['title' => 'Assistant Nurse', 'dept' => 'Health', 'salary' => 42000],
            ['title' => 'Transport Coordinator', 'dept' => 'Transport', 'salary' => 48000],
        ];

        $payrollInsert = [];

        foreach ($rolesData as $idx => $role) {
            $num = $idx + 1;
            $firstName = self::TEACHER_FIRST_NAMES[($num + 15) % count(self::TEACHER_FIRST_NAMES)];
            $lastName = self::LAST_NAMES[($num + 10) % count(self::LAST_NAMES)];
            $name = "{$firstName} {$lastName}";
            $email = "staff{$num}@greenvalley.ac.ke";

            $monthsAgo = ($num * 7) % 36;
            $staffCreatedAt = now()->subMonths($monthsAgo)->subDays(rand(1, 28));

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                    'email_verified_at' => $staffCreatedAt,
                    'status' => 'active',
                    'created_at' => $staffCreatedAt,
                    'updated_at' => $staffCreatedAt,
                ]
            );

            $staffRole = ($role['dept'] === 'Finance') ? 'Accountant' : 'Staff';
            if (in_array($staffRole, ['Accountant'], true) && ! $user->hasRole($staffRole)) {
                $user->assignRole($staffRole);
            }

            $staffDetails = StaffDetails::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'institution_id' => $institution->id,
                    'name' => $name,
                    'email' => $email,
                    'phone' => '+254 7'.rand(10000000, 99999999),
                    'staff_number' => 'STF-'.(2000 + $num),
                    'job_title' => $role['title'],
                    'department' => $role['dept'],
                    'employment_type' => 'full_time',
                    'salary' => (string) $role['salary'],
                    'hire_date' => $staffCreatedAt->toDateString(),
                    'is_active' => true,
                    'status' => 'active',
                    'created_at' => $staffCreatedAt,
                    'updated_at' => $staffCreatedAt,
                ]
            );

            // Generate 36 months of payroll history (Jan 2024 - Dec 2026)
            for ($month = 1; $month <= 36; $month++) {
                $payPeriodDate = now()->subMonths(37 - $month)->startOfMonth();
                $payPeriod = $payPeriodDate->toDateString();

                // Skip payroll months prior to staff hire date
                if ($payPeriodDate->lt($staffCreatedAt->startOfMonth())) {
                    continue;
                }

                $gross = (float) $role['salary'];
                $allowances = (float) rand(2000, 5000);
                $deductions = (float) rand(3000, 8000);
                $net = $gross + $allowances - $deductions;
                $paidAt = (clone $payPeriodDate)->addDays(28);

                $payrollInsert[] = [
                    'staff_details_id' => $staffDetails->id,
                    'institution_id' => $institution->id,
                    'recorded_by' => $institution->user_id,
                    'period' => $payPeriod,
                    'gross_amount' => $gross,
                    'allowances' => $allowances,
                    'deductions' => $deductions,
                    'net_amount' => $net,
                    'payment_method' => ($num % 2 === 0) ? 'bank_transfer' : 'mobile_money',
                    'reference' => 'PAY-'.strtoupper(Str::random(8)),
                    'status' => 'paid',
                    'paid_at' => $paidAt,
                    'created_at' => $paidAt,
                    'updated_at' => $paidAt,
                ];
            }
        }

        foreach (array_chunk($payrollInsert, 200) as $chunk) {
            DB::table('staff_payments')->insert($chunk);
        }
    }
}
