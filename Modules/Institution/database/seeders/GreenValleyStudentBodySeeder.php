<?php

namespace Modules\Institution\Database\Seeders;

use App\Models\User;
use Faker\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;

class GreenValleyStudentBodySeeder extends Seeder
{
    private const FIRST_NAMES_MALE = [
        'Brian', 'Kevin', 'Eric', 'Dennis', 'Ian', 'Victor', 'Collins', 'Felix', 'Emmanuel', 'Geoffrey',
        'Mark', 'Anthony', 'Alvin', 'Alex', 'Chris', 'Allan', 'Austin', 'Caleb', 'Derrick', 'Ethan',
        'Gideon', 'Ian', 'Jason', 'Joel', 'Joshua', 'Kelvin', 'Liam', 'Michael', 'Nathan', 'Noah',
    ];

    private const FIRST_NAMES_FEMALE = [
        'Sharon', 'Brenda', 'Faith', 'Joy', 'Mercy', 'Anita', 'Cynthia', 'Daisy', 'Ivy', 'Phyllis',
        'Stacy', 'Tracy', 'Vanessa', 'Winnie', 'Abigail', 'Angel', 'Beatrice', 'Caroline', 'Diana', 'Elsa',
        'Fiona', 'Grace', 'Hope', 'Irene', 'Jacqueline', 'Karen', 'Laura', 'Maureen', 'Nicole', 'Patricia',
    ];

    private const LAST_NAMES = [
        'Mwangi', 'Maina', 'Kamau', 'Kariuki', 'Njoroge', 'Kimani', 'Wanjiku', 'Otieno', 'Ochieng', 'Oduor',
        'Omondi', 'Anyango', 'Achieng', 'Kipchirchir', 'Kipkorir', 'Kipchumba', 'Chebet', 'Cherono', 'Mutua', 'Musyoka',
        'Wambua', 'Kilonzo', 'Wekesa', 'Wafula', 'Nekesa', 'Barasa', 'Juma', 'Nyongesa', 'Ouma', 'Okoth',
        'Mutiso', 'Kithinji', 'Mbae', 'Gichuru', 'Kagwe', 'Kinyua', 'Macharia', 'Mugo', 'Ndegwa', 'Nyaga',
    ];

    private const OCCUPATIONS = [
        'Software Engineer', 'Civil Engineer', 'Doctor', 'High School Teacher', 'Banker',
        'Accountant', 'Business Owner', 'Architect', 'Pharmacist', 'University Lecturer',
        'Marketing Manager', 'Human Resource Officer', 'Project Manager', 'Lawyer', 'Agronomist',
    ];

    public function run(): void
    {
        $institution = Institution::where('code', 'GVA001')->first();

        if (! $institution) {
            return;
        }

        $classes = SchoolClass::where('institution_id', $institution->id)->orderBy('id')->get();

        if ($classes->isEmpty()) {
            return;
        }

        $password = Hash::make('password');
        $faker = Factory::create();

        // 1. Create 400 Parents with registration dates spread across 2024-2026
        $parentUsersData = [];
        $parentDetailsData = [];

        for ($p = 1; $p <= 400; $p++) {
            $gender = ($p % 2 === 0) ? 'female' : 'male';
            $firstName = ($gender === 'male')
                ? self::FIRST_NAMES_MALE[($p - 1) % count(self::FIRST_NAMES_MALE)]
                : self::FIRST_NAMES_FEMALE[($p - 1) % count(self::FIRST_NAMES_FEMALE)];
            $lastName = self::LAST_NAMES[($p - 1) % count(self::LAST_NAMES)];
            $name = "{$firstName} {$lastName}";
            $email = "parent{$p}@greenvalley.ac.ke";

            // Spread parents registration: 2024 (1-150), 2025 (151-300), 2026 (301-400)
            $year = ($p <= 150) ? 2024 : (($p <= 300) ? 2025 : 2026);
            $createdAt = now()->setYear($year)->setMonth(rand(1, 12))->setDay(rand(1, 28));

            $parentUsersData[] = [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'email_verified_at' => $createdAt,
                'status' => 'active',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        DB::table('users')->insertOrIgnore($parentUsersData);

        $parentEmails = array_column($parentUsersData, 'email');
        $parentUsers = User::whereIn('email', $parentEmails)->pluck('id', 'email')->toArray();

        $roleId = DB::table('roles')->where('name', 'Parent')->value('id');

        if ($roleId) {
            $roleInserts = [];
            foreach ($parentUsers as $pId) {
                $roleInserts[] = [
                    'role_id' => $roleId,
                    'model_type' => User::class,
                    'model_id' => $pId,
                ];
            }

            foreach (array_chunk($roleInserts, 300) as $chunk) {
                DB::table('model_has_roles')->insertOrIgnore($chunk);
            }
        }

        $pIdx = 1;
        foreach ($parentUsers as $email => $pId) {
            $year = ($pIdx <= 150) ? 2024 : (($pIdx <= 300) ? 2025 : 2026);
            $createdAt = now()->setYear($year)->setMonth(rand(1, 12))->setDay(rand(1, 28));

            $parentDetailsData[] = [
                'parent_id' => $pId,
                'parent_phone' => '+254 7'.rand(10000000, 99999999),
                'parent_occupation' => self::OCCUPATIONS[($pIdx - 1) % count(self::OCCUPATIONS)],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
            $pIdx++;
        }

        foreach (array_chunk($parentDetailsData, 300) as $chunk) {
            DB::table('parent_details')->insertOrIgnore($chunk);
        }

        $parentUserIdsList = array_values($parentUsers);

        // 2. Create 1,008 Students spread across 3 Admission Cohorts (2024, 2025, 2026)
        $studentUsersData = [];
        $studentDetailsData = [];
        $studentCount = 1;

        foreach ($classes as $classIndex => $class) {
            $studentsInClass = 28;

            for ($s = 1; $s <= $studentsInClass; $s++) {
                $gender = ($studentCount % 2 === 0) ? 'female' : 'male';
                $firstName = ($gender === 'male')
                    ? self::FIRST_NAMES_MALE[($studentCount - 1) % count(self::FIRST_NAMES_MALE)]
                    : self::FIRST_NAMES_FEMALE[($studentCount - 1) % count(self::FIRST_NAMES_FEMALE)];
                $lastName = self::LAST_NAMES[($studentCount - 1) % count(self::LAST_NAMES)];
                $name = "{$firstName} {$lastName}";
                $email = "student{$studentCount}@greenvalley.ac.ke";

                // Cohort assignment: 2024 (1-350), 2025 (351-700), 2026 (701-1008)
                $admYear = ($studentCount <= 350) ? 2024 : (($studentCount <= 700) ? 2025 : 2026);
                $admDate = now()->setYear($admYear)->setMonth(1)->setDay(rand(5, 25));

                $studentUsersData[] = [
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'email_verified_at' => $admDate,
                    'status' => 'active',
                    'created_at' => $admDate,
                    'updated_at' => $admDate,
                ];

                $studentCount++;
            }
        }

        foreach (array_chunk($studentUsersData, 300) as $chunk) {
            DB::table('users')->insertOrIgnore($chunk);
        }

        $studentEmails = array_column($studentUsersData, 'email');
        $studentUsersMap = User::whereIn('email', $studentEmails)->pluck('id', 'email')->toArray();

        $studentRoleId = DB::table('roles')->where('name', 'Student')->value('id');

        if ($studentRoleId) {
            $sRoleInserts = [];
            foreach ($studentUsersMap as $sId) {
                $sRoleInserts[] = [
                    'role_id' => $studentRoleId,
                    'model_type' => User::class,
                    'model_id' => $sId,
                ];
            }

            foreach (array_chunk($sRoleInserts, 300) as $chunk) {
                DB::table('model_has_roles')->insertOrIgnore($chunk);
            }
        }

        $sIdx = 1;
        foreach ($classes as $class) {
            for ($s = 1; $s <= 28; $s++) {
                $email = "student{$sIdx}@greenvalley.ac.ke";
                $sId = $studentUsersMap[$email] ?? null;

                if (! $sId) {
                    $sIdx++;

                    continue;
                }

                $parentUserId = $parentUserIdsList[($sIdx - 1) % count($parentUserIdsList)];
                $gender = ($sIdx % 2 === 0) ? 'female' : 'male';
                $admYear = ($sIdx <= 350) ? 2024 : (($sIdx <= 700) ? 2025 : 2026);
                $admDate = now()->setYear($admYear)->setMonth(1)->setDay(rand(5, 25));

                $admNo = "GVA/{$admYear}/".sprintf('%04d', $sIdx);
                $stdNo = 'STD-'.(10000 + $sIdx);

                $studentDetailsData[] = [
                    'student_id' => $sId,
                    'institution_id' => $institution->id,
                    'parent_id' => $parentUserId,
                    'class_id' => (string) $class->id,
                    'admission_number' => $admNo,
                    'student_number' => $stdNo,
                    'phone' => '+254 7'.rand(10000000, 99999999),
                    'gender' => $gender,
                    'date_of_birth' => now()->subYears(rand(6, 17))->subDays(rand(1, 300))->toDateString(),
                    'address' => $faker->streetAddress(),
                    'city' => $faker->randomElement(['Nairobi', 'Mombasa', 'Nakuru', 'Eldoret', 'Kisumu']),
                    'state' => $faker->randomElement(['Nairobi County', 'Kiambu County', 'Machakos County', 'Kajiado County']),
                    'country' => 'Kenya',
                    'guardian_name' => 'Guardian of Student '.$sIdx,
                    'guardian_email' => 'guardian'.$sIdx.'@greenvalley.ac.ke',
                    'guardian_phone' => '+254 7'.rand(10000000, 99999999),
                    'guardian_relationship' => $faker->randomElement(['Father', 'Mother', 'Uncle', 'Aunt', 'Grandparent', 'Legal Guardian']),
                    'medical_conditions' => $faker->randomElement(['None', 'None', 'None', 'Asthma', 'Mild Allergies']),
                    'allergies' => $faker->randomElement(['None', 'None', 'Dust', 'Peanuts', 'Penicillin']),
                    'special_needs' => 'None',
                    'is_active' => true,
                    'enrollment_status' => 'active',
                    'created_at' => $admDate,
                    'updated_at' => $admDate,
                ];

                $sIdx++;
            }
        }

        foreach (array_chunk($studentDetailsData, 300) as $chunk) {
            DB::table('student_details')->insertOrIgnore($chunk);
        }
    }
}
