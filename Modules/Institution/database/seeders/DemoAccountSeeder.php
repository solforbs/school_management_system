<?php

namespace Modules\Institution\Database\Seeders;

use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Classes\Models\SchoolClass;
use Modules\Curriculum\Models\Curriculum;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\GradingBand;
use Modules\ReportCard\Models\ReportTemplate;
use Modules\ReportCard\Support\GradingScaleDefaults;
use Modules\Subject\Models\Subject;

/**
 * The one shared demo school every onboarding director can drop into,
 * populated so every part of the app has something real to show.
 *
 * This class sets up the institution's skeleton (director, subjects,
 * classes, faculty, timetable); the bulk data - students, exams, lessons,
 * finance - is filled in by the Demo* sub-seeders it calls, each of which
 * keys off the DEMO001 institution.
 *
 * The demo director (`demo@solforbs.com`) is never deleted, so a live demo
 * session survives a `demo:reset`; every other person here has an
 * `@demo.solforbs.com` address so the reset can find and clear them.
 */
class DemoAccountSeeder extends Seeder
{
    public const DIRECTOR_EMAIL = 'demo@solforbs.com';

    public const INSTITUTION_CODE = 'DEMO001';

    /** Sub-users (teachers, students, parents, staff) get an address on this domain. */
    public const SUB_USER_DOMAIN = 'demo.solforbs.com';

    public const STUDENTS_PER_CLASS = 8;

    public const TEACHER_COUNT = 14;

    /** @var array<string, array{code: string, compulsory: bool}> */
    public const SUBJECTS = [
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
    public const CLASS_LEVELS = ['Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Form 1', 'Form 2'];

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
        // Only needed on a genuinely empty database (tests). On a live
        // system the roles already exist and re-syncing every permission on
        // every six-hourly reset is pure waste.
        if (! DB::table('roles')->where('name', 'Director')->exists()) {
            $this->call([PermissionSeeder::class]);
        }

        $director = $this->director();
        $institution = $this->makeInstitution($director);
        $director->update(['active_institution_id' => $institution->id]);

        $this->curricula($institution);
        $this->gradingScale($institution);
        $this->reportTemplate($institution);

        $subjects = $this->subjects($institution);
        $classes = $this->classes($institution);
        $teachers = $this->teachers($institution, self::TEACHER_COUNT);
        $this->assignClassTeachers($classes, $teachers);
        $this->assignSubjectTeachers($institution, $classes, $subjects, $teachers);
        $this->staff($institution);
        $this->timetable($institution, $classes, $subjects, $teachers);

        // The bulk data - each sub-seeder finds DEMO001 for itself.
        $this->call([
            DemoStudentsSeeder::class,
            DemoExamsSeeder::class,
            DemoLessonsSeeder::class,
            DemoFinanceSeeder::class,
        ]);
    }

    public static function personName(int $seed): string
    {
        return self::FIRST_NAMES[$seed % count(self::FIRST_NAMES)]
            .' '.self::LAST_NAMES[$seed % count(self::LAST_NAMES)];
    }

    /**
     * The 8-4-4 letter grade for a percentage.
     */
    public static function gradeForPercentage(float $percentage): ?string
    {
        foreach (GradingScaleDefaults::eightFourFour() as $band) {
            if ($percentage >= $band['min_percentage'] && $percentage <= $band['max_percentage']) {
                return $band['grade'];
            }
        }

        return null;
    }

    /**
     * The demo institution, for the Demo* sub-seeders to key off.
     */
    public static function institution(): ?Institution
    {
        return Institution::where('code', self::INSTITUTION_CODE)->first();
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

    private function makeInstitution(User $director): Institution
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
                // The top plan, so billing and limits read as a fully
                // subscribed school. Modules and features don't depend on
                // it - a demo institution has all of them regardless (see
                // Institution::hasModule()).
                'subscription_plan' => Plan::where('slug', 'enterprise')->value('id'),
                'subscription_started_at' => now(),
                'subscription_expires_at' => now()->addYear(),
            ],
        );
    }

    private function curricula(Institution $institution): void
    {
        Curriculum::firstOrCreate(
            ['institution_id' => $institution->id, 'name' => 'Competency Based Curriculum'],
            ['system' => 'cbc', 'status' => 'active'],
        );
        Curriculum::firstOrCreate(
            ['institution_id' => $institution->id, 'name' => '8-4-4 System'],
            ['system' => '844', 'status' => 'active'],
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

    private function reportTemplate(Institution $institution): void
    {
        ReportTemplate::firstOrCreate(
            ['institution_id' => $institution->id],
            [
                'opening_text' => 'It is a pleasure to share your child\'s progress this term.',
                'closing_text' => 'Thank you for your continued partnership. School reopens as per the calendar.',
                'signatory_name' => 'Mr. Demo Principal',
                'signatory_title' => 'Principal',
            ],
        );
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
        $cbc = Curriculum::where('institution_id', $institution->id)->where('system', 'cbc')->value('id');
        $legacy = Curriculum::where('institution_id', $institution->id)->where('system', '844')->value('id');

        return collect(self::CLASS_LEVELS)->map(fn (string $level) => SchoolClass::firstOrCreate(
            ['institution_id' => $institution->id, 'name' => $level.' East'],
            [
                'level' => $level,
                'capacity' => 35,
                'curriculum_id' => str_starts_with($level, 'Form') ? $legacy : $cbc,
            ],
        ));
    }

    /**
     * Bulk-create demo users under one role and hand back the models,
     * keyed by e-mail. Uses raw inserts (and one shared bcrypt hash) so a
     * hundred-plus sub-users don't cost a hundred-plus round trips.
     *
     * @param  array<string, string>  $emailToName
     * @return Collection<string, User>
     */
    public static function bulkUsers(array $emailToName, string $roleName): Collection
    {
        $password = Hash::make('password');
        $now = now();

        $rows = collect($emailToName)->map(fn (string $name, string $email) => [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'email_verified_at' => $now,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();

        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('users')->insertOrIgnore($chunk);
        }

        $users = User::whereIn('email', array_keys($emailToName))->get();
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');

        if ($roleId) {
            $roleRows = $users->map(fn (User $u) => [
                'role_id' => $roleId, 'model_type' => User::class, 'model_id' => $u->id,
            ])->all();

            foreach (array_chunk($roleRows, 300) as $chunk) {
                DB::table('model_has_roles')->insertOrIgnore($chunk);
            }
        }

        return $users->keyBy('email');
    }

    /**
     * @return Collection<int, User>
     */
    private function teachers(Institution $institution, int $count)
    {
        $emailToName = collect(range(1, $count))
            ->mapWithKeys(fn (int $i) => ["teacher{$i}@".self::SUB_USER_DOMAIN => self::personName($i)])
            ->all();

        $teachers = self::bulkUsers($emailToName, 'Teacher');

        $now = now();
        $detailRows = collect($emailToName)->keys()->map(function (string $email, int $idx) use ($teachers, $institution, $now) {
            $i = $idx + 1;

            return [
                'teacher_id' => $teachers[$email]->id,
                'institution_id' => $institution->id,
                'phone' => '+254 71'.str_pad((string) ($i * 111111), 7, '0', STR_PAD_LEFT),
                'employee_number' => 'TCH-'.(100 + $i),
                'department' => ['Sciences', 'Languages', 'Humanities', 'Mathematics'][$i % 4],
                'qualification' => 'Bachelor of Education',
                'hire_date' => $now->copy()->subMonths($i * 3)->toDateString(),
                'is_active' => true,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        DB::table('teacher_details')->insertOrIgnore($detailRows);

        return $teachers->values();
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
        $now = now();
        $rows = [];

        foreach ($classes->values() as $ci => $class) {
            foreach ($subjects->values() as $si => $subject) {
                $rows[] = [
                    'institution_id' => $institution->id,
                    'class_id' => $class->id,
                    'subject_id' => $subject->id,
                    'teacher_id' => $teachers[($ci + $si) % $teachers->count()]->id,
                    'assigned_by' => $institution->user_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('subject_teachers')->insertOrIgnore($chunk);
        }
    }

    private function staff(Institution $institution): void
    {
        $roles = [
            ['Senior Accountant', 'Finance', 'Accountant', 85000],
            ['School Nurse', 'Health', 'Staff', 62000],
            ['IT Administrator', 'ICT', 'Staff', 70000],
            ['Head Librarian', 'Library', 'Staff', 55000],
            ['Transport Coordinator', 'Operations', 'Staff', 48000],
        ];

        $emailToName = collect($roles)
            ->mapWithKeys(fn (array $r, int $i) => ['staff'.($i + 1).'@'.self::SUB_USER_DOMAIN => self::personName($i + 21)])
            ->all();

        // Only the accountant gets a portal role; the rest are records-only.
        $accountants = ['staff1@'.self::SUB_USER_DOMAIN => self::personName(21)];
        self::bulkUsers($accountants, 'Accountant');
        $users = self::bulkUsers($emailToName, 'Staff');

        $now = now();
        $rows = [];

        foreach ($roles as $i => [$title, $dept, $role, $salary]) {
            $n = $i + 1;
            $user = $users['staff'.$n.'@'.self::SUB_USER_DOMAIN];

            $rows[] = [
                'user_id' => $user->id,
                'institution_id' => $institution->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => '+254 72'.str_pad((string) ($n * 222222), 7, '0', STR_PAD_LEFT),
                'staff_number' => 'STF-'.(200 + $n),
                'job_title' => $title,
                'department' => $dept,
                'employment_type' => 'full_time',
                'salary' => (string) $salary,
                'hire_date' => $now->copy()->subMonths($n * 4)->toDateString(),
                'is_active' => true,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('staff_details')->insertOrIgnore($rows);
    }

    /**
     * @param  Collection<int, SchoolClass>  $classes
     * @param  Collection<int, Subject>  $subjects
     * @param  Collection<int, User>  $teachers
     */
    private function timetable(Institution $institution, $classes, $subjects, $teachers): void
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $periods = [['08:00', '08:40'], ['08:40', '09:20'], ['09:40', '10:20'], ['10:20', '11:00'], ['11:20', '12:00'], ['12:00', '12:40']];
        $now = now();
        $rows = [];

        foreach ($classes->values() as $class) {
            foreach ($days as $di => $day) {
                foreach ($periods as $pi => [$start, $end]) {
                    $subject = $subjects[($di * count($periods) + $pi) % $subjects->count()];

                    $rows[] = [
                        'institution_id' => $institution->id,
                        'class_id' => $class->id,
                        'day_of_week' => $day,
                        'start_time' => $start,
                        'end_time' => $end,
                        'teacher_id' => $teachers[($di + $pi) % $teachers->count()]->id,
                        'class_name' => $class->name,
                        'subject' => $subject->name,
                        'subject_id' => $subject->id,
                        'room' => 'Rm '.($class->id * 10 + $pi),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        // The reset clears the demo institution's rows first, so a plain
        // insert is safe and there is no unique key to lean on anyway.
        DB::table('timetable_entries')->where('institution_id', $institution->id)->delete();

        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('timetable_entries')->insert($chunk);
        }
    }
}
