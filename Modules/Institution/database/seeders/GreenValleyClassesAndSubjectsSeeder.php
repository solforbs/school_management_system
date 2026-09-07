<?php

namespace Modules\Institution\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Subject\Models\Subject;

class GreenValleyClassesAndSubjectsSeeder extends Seeder
{
    /**
     * Standard subjects list with codes and compulsory status.
     */
    private const SUBJECTS = [
        'Mathematics' => ['code' => 'MATH', 'is_compulsory' => true],
        'English Language' => ['code' => 'ENG', 'is_compulsory' => true],
        'Kiswahili' => ['code' => 'KISW', 'is_compulsory' => true],
        'Biology' => ['code' => 'BIOL', 'is_compulsory' => false],
        'Chemistry' => ['code' => 'CHEM', 'is_compulsory' => false],
        'Physics' => ['code' => 'PHYS', 'is_compulsory' => false],
        'History & Government' => ['code' => 'HIST', 'is_compulsory' => false],
        'Geography' => ['code' => 'GEOG', 'is_compulsory' => false],
        'Christian Religious Education' => ['code' => 'CRE', 'is_compulsory' => false],
        'Islamic Religious Education' => ['code' => 'IRE', 'is_compulsory' => false],
        'Business Studies' => ['code' => 'BST', 'is_compulsory' => false],
        'Agriculture' => ['code' => 'AGRI', 'is_compulsory' => false],
        'Computer Studies' => ['code' => 'COMP', 'is_compulsory' => false],
        'Home Science' => ['code' => 'HSC', 'is_compulsory' => false],
        'Art & Design' => ['code' => 'ART', 'is_compulsory' => false],
        'Music' => ['code' => 'MUS', 'is_compulsory' => false],
        'French' => ['code' => 'FREN', 'is_compulsory' => false],
        'German' => ['code' => 'GERM', 'is_compulsory' => false],
        'Physical Education' => ['code' => 'PE', 'is_compulsory' => true],
        'Life Skills Education' => ['code' => 'LSE', 'is_compulsory' => true],
        'Integrated Science' => ['code' => 'ISCI', 'is_compulsory' => true],
        'Social Studies' => ['code' => 'SST', 'is_compulsory' => true],
        'Creative Arts & Sports' => ['code' => 'CAS', 'is_compulsory' => true],
    ];

    public function run(): void
    {
        $institution = Institution::where('code', 'GVA001')->first();

        if (! $institution) {
            return;
        }

        // 1. Seed Subjects
        foreach (self::SUBJECTS as $name => $meta) {
            Subject::firstOrCreate(
                [
                    'institution_id' => $institution->id,
                    'name' => $name,
                ],
                [
                    'code' => $meta['code'],
                    'is_compulsory' => $meta['is_compulsory'],
                    'is_active' => true,
                ]
            );
        }

        // 2. Seed 36 Classes across 12 Grade Levels (East, West, North streams)
        $streams = ['East', 'West', 'North'];

        for ($grade = 1; $grade <= 12; $grade++) {
            $levelName = $grade <= 8 ? "Grade {$grade}" : 'Form '.($grade - 8);

            foreach ($streams as $stream) {
                $className = "{$levelName} {$stream}";

                SchoolClass::firstOrCreate(
                    [
                        'institution_id' => $institution->id,
                        'name' => $className,
                    ],
                    [
                        'level' => $levelName,
                        'capacity' => 35,
                    ]
                );
            }
        }
    }
}
