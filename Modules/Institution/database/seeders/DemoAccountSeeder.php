<?php

namespace Modules\Institution\Database\Seeders;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Actions\SaveExamSitting;
use Modules\Examinations\Models\Examination;
use Modules\FeeManagement\Models\Fee;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\GradingBand;
use Modules\ReportCard\Support\GradingScaleDefaults;
use Modules\Result\Models\Result;
use Modules\Staff\Models\StaffDetails;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;
use Modules\Subject\Models\SubjectTeacher;
use Modules\Teacher\Models\TeacherDetails;

/**
 * The one shared demo school every onboarding director can drop into. Kept
 * deliberately lean (a few classes, ~50 students) so `demo:reset` can wipe
 * and rebuild it in seconds.
 *
 * The demo director (`demo@solforbs.com`) is never deleted so a live demo
 * session keeps working across a reset; every other person here has an
 * `@demo.solforbs.com` address so the reset can find and clear them.
 */
class DemoAccountSeeder extends Seeder
{
    public const DIRECTOR_EMAIL = 'demo@solforbs.com';

    public const INSTITUTION_CODE = 'DEMO001';

    /** Sub-users (teachers, students, parents, staff) get an address on this domain. */
    public const SUB_USER_DOMAIN = 'demo.solforbs.com';

    /** @var array<string, array{code: string, compulsory: bool}> */
    private const SUBJECTS = [
        'Mathematics' => ['code' => 'MATH', 'compulsory' => true],
        'English Language' => ['code' => 'ENG', 'compulsory' => true],
        'Kiswahili' => ['code' => 'KISW', 'compulsory' => true],
        'Integrated Science' => ['code' => 'ISCI', 'compulsory' => true],
        'Social Studies' => ['code' => 'SST', 'compulsory' => true],
        'Biology' => ['code' => 'BIOL', 'compulsory' => false],
        'Chemistry' => ['code' => 'CHEM', 'compulsory' => false],
        'Physics' => ['code' => 'PHYS', 'compulsory' => false],
        'Geography' => ['code' => 'GEOG', 'compulsory' => false],
        'History & Government' => ['code' => 'HIST', 'compulsory' => false],
        'Christian Religious Education' => ['code' => 'CRE', 'compulsory' => false],
        'Business Studies' => ['code' => 'BST', 'compulsory' => false],
        'Agriculture' => ['code' => 'AGRI', 'compulsory' => false],
        'Computer Studies' => ['code' => 'COMP', 'compulsory' => false],
        'Creative Arts & Sports' => ['code' => 'CAS', 'compulsory' => true],
        'Physical Education' => ['code' => 'PE', 'compulsory' => true],
    ];

    /** @var list<string> */
    private const FIRST_NAMES = [
        'Brian', 'Sharon', 'Kevin', 'Faith', 'Eric', 'Mercy', 'Victor', 'Cynthia', 'Collins', 'Daisy',
        'Mark', 'Winnie', 'Allan', 'Beatrice', 'Caleb', 'Diana', 'Derrick', 'Grace', 'Ethan', 'Irene',
        'Joel', 'Karen', 'Joshua', 'Laura', 'Nathan', 'Nicole', 'Michael', 'Patricia', 'Noah', 'Angel',
    ];

    /** @var list<string> */
    private const LAST_NAMES = [
        'Mwangi', 'Otieno', 'Kamau', 'Achieng', 'Njoroge', 'Wanjiku', 'Ochieng', 'Chebet', 'Mutua', 'Wafula',
        'Kilonzo', 'Barasa', 'Juma', 'Ouma', 'Kinyua', 'Macharia', 'Ndegwa', 'Nyaga', 'Kariuki', 'Kimani',
    ];

    public function run(): void
    {
        $this->call([PermissionSeeder::class, PlanSeeder::class]);

        $director = $this->director();
        $institution = $this->institution($director);
        $director->update(['active_institution_id' => $institution->id]);

        $this->gradingScale($institution);
        $subjects = $this->subjects($institution);
        $classes = $this->classes($institution);
        $teachers = $this->teachers($institution, 12);
        $this->assignClassTeachers($classes, $teachers);
        $this->assignSubjectTeachers($institution, $classes, $subjects, $teachers);
        $this->staff($institution, 4);
        $students = $this->studentBody($institution, $classes, 8);
        $this->timetable($institution, $classes, $subjects, $teachers);
        $this->examAndResults($institution, $classes, $subjects, $students);
        $this->fees($institution, $students);
    }

    private function director(): User
    {
        $director = User::updateOrCreate(
            ['email' => self::DIRECTOR_EMAIL],
            [
                'name' => 'Demo Director',
                'password' => Hash::make(self::DIRECTOR_EMAIL),
                'email_verified_at' => now(),
                'status' => 'active',
            ],
        );

        if (! $director->hasRole('Director')) {
            $director->assignRole('Director');
        }

        return $director;
    }

    private function institution(User $director): Institution
    {
        return Institution::updateOrCreate(
            ['code' => self::INSTITUTION_CODE],
            [
                'user_id' => $director->id,
                'name' => 'Riverside Demo Academy',
                'type' => 'Primary & Secondary School',
                'email' => 'hello@riverside.demo.solforbs.com',
                'phone' => '+254 700 111 222',
                'website' => 'https://riverside.demo.solforbs.com',
                'country' => 'Kenya',
                'county' => 'Nairobi',
                'city' => 'Nairobi',
                'physical_address' => 'Riverside Drive, Nairobi',
                'principal_name' => 'Mr. Demo Principal',
                'education_level' => 'Primary & Secondary',
                'min_electives' => 1,
                'max_electives' => 4,
                'timezone' => 'Africa/Nairobi',
                'is_active' => true,
                'is_demo' => true,
                'status' => 'active',
                'is_approved' => true,
                'approved_at' => now(),
                'subscription_started_at' => now(),
                'subscription_expires_at' => now()->addYear(),
            ],
        );
    }

    private function gradingScale(Institution $institution): void
    {
        foreach (GradingScaleDefaults::eightFourFour() as $band) {
            GradingBand::firstOrCreate(
                ['institution_id' => $institution->id, 'curriculum_id' => null, 'grade' => $band['grade']],
                $band,
            );
        }
    }

    /**
     * @return Collection<int, Subject>
     */
    private function subjects(Institution $institution)
    {
        return collect(self::SUBJECTS)->map(fn (array $meta, string $name) => Subject::firstOrCreate(
            ['institution_id' => $institution->id, 'name' => $name],
            ['code' => $meta['code'], 'is_compulsory' => $meta['compulsory'], 'is_active' => true],
        ))->values();
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    private function classes(Institution $institution)
    {
        return collect(['Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Form 1', 'Form 2'])
            ->map(fn (string $level) => SchoolClass::firstOrCreate(
                ['institution_id' => $institution->id, 'name' => $level.' East'],
                ['level' => $level, 'capacity' => 35],
            ));
    }

    /**
     * @return Collection<int, User>
     */
    private function teachers(Institution $institution, int $count)
    {
        return collect(range(1, $count))->map(function (int $i) use ($institution) {
            $user = User::updateOrCreate(
                ['email' => "teacher{$i}@".self::SUB_USER_DOMAIN],
                [
                    'name' => $this->personName($i),
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'status' => 'active',
                ],
            );

            if (! $user->hasRole('Teacher')) {
                $user->assignRole('Teacher');
            }

            TeacherDetails::firstOrCreate(
                ['teacher_id' => $user->id],
                [
                    'institution_id' => $institution->id,
                    'phone' => '+254 71'.str_pad((string) ($i * 111111), 7, '0', STR_PAD_LEFT),
                    'employee_number' => 'TCH-'.(100 + $i),
                    'department' => ['Sciences', 'Languages', 'Humanities', 'Mathematics'][$i % 4],
                    'qualification' => 'Bachelor of Education',
                    'hire_date' => now()->subMonths($i * 3)->toDateString(),
                    'is_active' => true,
                    'status' => 'active',
                ],
            );

            return $user;
        });
    }

    /**
     * @param  Collection<int, SchoolClass>  $classes
     * @param  Collection<int, User>  $teachers
     */
    private function assignClassTeachers($classes, $teachers): void
    {
        $classes->values()->each(fn (SchoolClass $class, int $i) => $class->update([
            'class_teacher_id' => $teachers[$i % $teachers->count()]->id,
        ]));
    }

    /**
     * @param  Collection<int, SchoolClass>  $classes
     * @param  Collection<int, Subject>  $subjects
     * @param  Collection<int, User>  $teachers
     */
    private function assignSubjectTeachers(Institution $institution, $classes, $subjects, $teachers): void
    {
        foreach ($classes->values() as $ci => $class) {
            foreach ($subjects->values() as $si => $subject) {
                SubjectTeacher::firstOrCreate([
                    'class_id' => $class->id,
                    'subject_id' => $subject->id,
                    'teacher_id' => $teachers[($ci + $si) % $teachers->count()]->id,
                ], [
                    'institution_id' => $institution->id,
                    'assigned_by' => $institution->user_id,
                ]);
            }
        }
    }

    private function staff(Institution $institution, int $count): void
    {
        $roles = [
            ['Senior Accountant', 'Finance', 'Accountant', 85000],
            ['School Nurse', 'Health', 'Staff', 62000],
            ['IT Administrator', 'ICT', 'Staff', 70000],
            ['Head Librarian', 'Library', 'Staff', 55000],
        ];

        foreach (array_slice($roles, 0, $count) as $i => [$title, $dept, $role, $salary]) {
            $n = $i + 1;
            $user = User::updateOrCreate(
                ['email' => "staff{$n}@".self::SUB_USER_DOMAIN],
                [
                    'name' => $this->personName($n + 20),
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'status' => 'active',
                ],
            );

            if ($role === 'Accountant' && ! $user->hasRole('Accountant')) {
                $user->assignRole('Accountant');
            }

            StaffDetails::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'institution_id' => $institution->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => '+254 72'.str_pad((string) ($n * 222222), 7, '0', STR_PAD_LEFT),
                    'staff_number' => 'STF-'.(200 + $n),
                    'job_title' => $title,
                    'department' => $dept,
                    'employment_type' => 'full_time',
                    'salary' => (string) $salary,
                    'hire_date' => now()->subMonths($n * 4)->toDateString(),
                    'is_active' => true,
                    'status' => 'active',
                ],
            );
        }
    }

    /**
     * @param  Collection<int, SchoolClass>  $classes
     * @return Collection<int, StudentDetails>
     */
    private function studentBody(Institution $institution, $classes, int $perClass)
    {
        $studentRoleId = DB::table('roles')->where('name', 'Student')->value('id');
        $parentRoleId = DB::table('roles')->where('name', 'Parent')->value('id');
        $details = collect();
        $n = 0;

        foreach ($classes->values() as $class) {
            for ($s = 1; $s <= $perClass; $s++) {
                $n++;

                $parent = User::updateOrCreate(
                    ['email' => "parent{$n}@".self::SUB_USER_DOMAIN],
                    [
                        'name' => $this->personName($n + 40),
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                        'status' => 'active',
                    ],
                );
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $parentRoleId, 'model_type' => User::class, 'model_id' => $parent->id,
                ]);
                DB::table('parent_details')->insertOrIgnore([
                    'parent_id' => $parent->id,
                    'parent_phone' => '+254 73'.str_pad((string) ($n * 333333), 7, '0', STR_PAD_LEFT),
                    'parent_occupation' => ['Engineer', 'Doctor', 'Teacher', 'Trader', 'Farmer'][$n % 5],
                ]);

                $student = User::updateOrCreate(
                    ['email' => "student{$n}@".self::SUB_USER_DOMAIN],
                    [
                        'name' => $this->personName($n),
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                        'status' => 'active',
                    ],
                );
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $studentRoleId, 'model_type' => User::class, 'model_id' => $student->id,
                ]);

                $details->push(StudentDetails::updateOrCreate(
                    ['student_id' => $student->id],
                    [
                        'institution_id' => $institution->id,
                        'parent_id' => $parent->id,
                        'class_id' => (string) $class->id,
                        'admission_number' => 'DEMO/'.now()->year.'/'.sprintf('%03d', $n),
                        'student_number' => 'STD-'.(1000 + $n),
                        'phone' => '+254 74'.str_pad((string) ($n * 444444), 7, '0', STR_PAD_LEFT),
                        'gender' => $n % 2 === 0 ? 'female' : 'male',
                        'date_of_birth' => now()->subYears(rand(7, 16))->subDays(rand(1, 300))->toDateString(),
                        'country' => 'Kenya',
                        'medical_conditions' => 'None',
                        'allergies' => 'None',
                        'special_needs' => 'None',
                        'is_active' => true,
                        'enrollment_status' => 'active',
                    ],
                ));
            }
        }

        return $details;
    }

    /**
     * @param  Collection<int, SchoolClass>  $classes
     * @param  Collection<int, Subject>  $subjects
     * @param  Collection<int, User>  $teachers
     */
    private function timetable(Institution $institution, $classes, $subjects, $teachers): void
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $periods = [['08:00', '08:40'], ['08:40', '09:20'], ['09:40', '10:20'], ['10:20', '11:00'], ['11:20', '12:00']];

        foreach ($classes->values() as $class) {
            foreach ($days as $di => $day) {
                foreach ($periods as $pi => [$start, $end]) {
                    $subject = $subjects[($di * count($periods) + $pi) % $subjects->count()];

                    DB::table('timetable_entries')->updateOrInsert(
                        [
                            'institution_id' => $institution->id,
                            'class_id' => $class->id,
                            'day_of_week' => $day,
                            'start_time' => $start,
                        ],
                        [
                            'teacher_id' => $teachers[($di + $pi) % $teachers->count()]->id,
                            'class_name' => $class->name,
                            'subject' => $subject->name,
                            'subject_id' => $subject->id,
                            'end_time' => $end,
                            'room' => 'Rm '.($class->id * 10 + $pi),
                            'updated_at' => now(),
                            'created_at' => now(),
                        ],
                    );
                }
            }
        }
    }

    /**
     * @param  Collection<int, SchoolClass>  $classes
     * @param  Collection<int, Subject>  $subjects
     * @param  Collection<int, StudentDetails>  $students
     */
    private function examAndResults(Institution $institution, $classes, $subjects, $students): void
    {
        $sitting = SaveExamSitting::handle([
            'title' => 'End Term Exam',
            'term' => 'Term 2',
            'exam_type' => 'end_term',
            'academic_year' => now()->year,
        ], $institution->id);

        // Marks for the first two classes across the compulsory subjects, so
        // the results and report-card screens have something to show.
        $gradedSubjects = $subjects->take(5);

        foreach ($classes->take(2) as $class) {
            $roll = $students->where('class_id', (string) $class->id);

            foreach ($gradedSubjects as $subject) {
                $paper = Examination::query()
                    ->where('sitting_id', $sitting->id)
                    ->where('class_id', $class->id)
                    ->where('subject_id', $subject->id)
                    ->first();

                if (! $paper) {
                    continue;
                }

                foreach ($roll as $detail) {
                    $marks = rand(28, 95);

                    Result::updateOrCreate(
                        [
                            'examination_id' => $paper->id,
                            'student_id' => $detail->student_id,
                            'subject_id' => $subject->id,
                        ],
                        [
                            'institution_id' => $institution->id,
                            'class_id' => $class->id,
                            'marks_obtained' => $marks,
                            'grade' => $this->grade($marks, (int) $paper->total_marks),
                            'recorded_by' => $institution->user_id,
                        ],
                    );
                }
            }
        }
    }

    /**
     * @param  Collection<int, StudentDetails>  $students
     */
    private function fees(Institution $institution, $students): void
    {
        foreach ($students as $detail) {
            $amount = 25000.00;
            $rand = rand(1, 100);
            $paid = $rand <= 70 ? $amount : ($rand <= 90 ? round($amount * 0.5, -2) : 0.0);

            Fee::updateOrCreate(
                [
                    'institution_id' => $institution->id,
                    'student_id' => $detail->student_id,
                    'title' => 'Term 2 '.now()->year.' Fees',
                ],
                [
                    'parent_id' => $detail->parent_id,
                    'fee_type' => 'tuition',
                    'amount' => $amount,
                    'amount_paid' => $paid,
                    'due_date' => now()->addMonths(1)->toDateString(),
                ],
            );
        }
    }

    private function personName(int $seed): string
    {
        return self::FIRST_NAMES[$seed % count(self::FIRST_NAMES)]
            .' '.self::LAST_NAMES[$seed % count(self::LAST_NAMES)];
    }

    private function grade(int $marks, int $outOf): ?string
    {
        if ($outOf <= 0) {
            return null;
        }

        $pct = $marks / $outOf * 100;

        foreach (GradingScaleDefaults::eightFourFour() as $band) {
            if ($pct >= $band['min_percentage'] && $pct <= $band['max_percentage']) {
                return $band['grade'];
            }
        }

        return null;
    }
}
