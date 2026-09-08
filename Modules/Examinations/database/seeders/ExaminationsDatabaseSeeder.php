<?php

namespace Modules\Examinations\Database\Seeders;

use Illuminate\Database\Seeder;

class ExaminationsDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            ExamTimetableSeeder::class,
        ]);
    }
}
