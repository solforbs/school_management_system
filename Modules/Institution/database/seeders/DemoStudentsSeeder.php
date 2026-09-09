<?php

namespace Modules\Institution\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Classes\Models\SchoolClass;
use Modules\Subject\Models\Subject;

/**
 * The demo school's roll: students, a parent per student, and the elective
 * choices the senior classes have made. Written with bulk inserts so a
 * `demo:reset` stays quick.
 */
class DemoStudentsSeeder extends Seeder
{
    public function run(): void
    {
        $institution = DemoAccountSeeder::institution();

        if (! $institution) {
            return;
        }

        $classes = SchoolClass::where('institution_id', $institution->id)->orderBy('id')->get();

        if ($classes->isEmpty()) {
            return;
        }

        $occupations = ['Engineer', 'Doctor', 'Teacher', 'Trader', 'Farmer', 'Nurse', 'Accountant', 'Driver'];
        $domain = DemoAccountSeeder::SUB_USER_DOMAIN;
        $perClass = DemoAccountSeeder::STUDENTS_PER_CLASS;
        $total = $classes->count() * $perClass;

        // 1. Parents + students as two bulk user batches.
        $parentNames = [];
        $studentNames = [];
        for ($n = 1; $n <= $total; $n++) {
            $parentNames["parent{$n}@{$domain}"] = DemoAccountSeeder::personName($n + 40);
            $studentNames["student{$n}@{$domain}"] = DemoAccountSeeder::personName($n);
        }

        $parents = DemoAccountSeeder::bulkUsers($parentNames, 'Parent');
        $students = DemoAccountSeeder::bulkUsers($studentNames, 'Student');

        // 2. parent_details + student_details.
        $now = now();
        $parentDetails = [];
        $studentDetails = [];
        $n = 0;

        foreach ($classes as $class) {
            for ($s = 1; $s <= $perClass; $s++) {
                $n++;
                $parent = $parents["parent{$n}@{$domain}"];
                $student = $students["student{$n}@{$domain}"];

                $parentDetails[] = [
                    'parent_id' => $parent->id,
                    'parent_phone' => '+254 73'.str_pad((string) ($n * 333333), 7, '0', STR_PAD_LEFT),
                    'parent_occupation' => $occupations[$n % count($occupations)],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $studentDetails[] = [
                    'student_id' => $student->id,
                    'institution_id' => $institution->id,
                    'parent_id' => $parent->id,
                    'class_id' => (string) $class->id,
                    'admission_number' => 'DEMO/'.$now->year.'/'.sprintf('%03d', $n),
                    'student_number' => 'STD-'.(1000 + $n),
                    'phone' => '+254 74'.str_pad((string) ($n * 444444), 7, '0', STR_PAD_LEFT),
                    'gender' => $n % 2 === 0 ? 'female' : 'male',
                    'date_of_birth' => $now->copy()->subYears(rand(7, 16))->subDays(rand(1, 300))->toDateString(),
                    'address' => 'House '.$n.', Riverside',
                    'city' => 'Nairobi',
                    'country' => 'Kenya',
                    'guardian_name' => $parent->name,
                    'guardian_phone' => '+254 73'.str_pad((string) ($n * 333333), 7, '0', STR_PAD_LEFT),
                    'guardian_relationship' => $n % 2 === 0 ? 'Mother' : 'Father',
                    'medical_conditions' => $n % 9 === 0 ? 'Asthma' : 'None',
                    'allergies' => $n % 7 === 0 ? 'Peanuts' : 'None',
                    'special_needs' => 'None',
                    'is_active' => true,
                    'enrollment_status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($parentDetails, 300) as $chunk) {
            DB::table('parent_details')->insertOrIgnore($chunk);
        }
        foreach (array_chunk($studentDetails, 300) as $chunk) {
            DB::table('student_details')->insertOrIgnore($chunk);
        }

        $this->electives($institution, $classes);
    }

    /**
     * Senior classes (Form 1/2) each pick two electives, so the Selections
     * screen has data.
     *
     * @param  Collection<int, SchoolClass>  $classes
     */
    private function electives($institution, $classes): void
    {
        $electives = Subject::where('institution_id', $institution->id)
            ->where('is_compulsory', false)->orderBy('id')->get();

        if ($electives->isEmpty()) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($classes->filter(fn (SchoolClass $c) => str_starts_with((string) $c->level, 'Form'))->values() as $ci => $class) {
            $roll = DB::table('student_details')->where('class_id', $class->id)->pluck('student_id');

            foreach ($roll->values() as $si => $studentId) {
                $picks = collect([
                    $electives[($ci + $si) % $electives->count()]->id,
                    $electives[($ci + $si + 3) % $electives->count()]->id,
                ])->unique();

                foreach ($picks as $subjectId) {
                    $rows[] = [
                        'institution_id' => $institution->id,
                        'student_id' => $studentId,
                        'class_id' => $class->id,
                        'subject_id' => $subjectId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('subject_selections')->insertOrIgnore($chunk);
        }
    }
}
