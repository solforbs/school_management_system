<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Examinations\Models\Examination;
use Modules\Examinations\Models\ExamSitting;
use Modules\Institution\Database\Seeders\DemoAccountSeeder;
use Modules\Institution\Models\Institution;
use Modules\Result\Models\Result;

/**
 * Wipes the shared demo school and rebuilds it from the seeder, so whatever
 * onboarding directors changed while poking around is gone. Runs on a
 * schedule (see routes/console.php) and is also how the demo is created the
 * first time.
 */
class ResetDemoAccount extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Reset the shared demo school to its seeded state';

    public function handle(): int
    {
        DB::transaction(function () {
            Institution::demo()->each(function (Institution $institution) {
                // `results` is reachable from an institution delete by two
                // cascade paths (institution_id and examination_id), which
                // InnoDB refuses. Clear the exam graph explicitly first;
                // everything else on institution_id is single-path.
                Result::where('institution_id', $institution->id)->delete();
                Examination::where('institution_id', $institution->id)->delete();
                ExamSitting::where('institution_id', $institution->id)->delete();

                // The rest - classes, subjects, students, fees, timetable,
                // grading bands, staff, devices - cascades off institution_id.
                $institution->delete();
            });

            // The seeded teachers/students/parents/staff are users, which
            // have no FK to the institution - clear them by their address.
            // The demo director (demo@solforbs.com, a different domain) is
            // left alone so a live demo session survives the reset.
            User::where('email', 'like', '%@'.DemoAccountSeeder::SUB_USER_DOMAIN)->delete();
        });

        $this->callSilent('db:seed', [
            '--class' => DemoAccountSeeder::class,
            '--force' => true,
        ]);

        $institution = Institution::demo()->first();
        $summary = [
            'institution_id' => $institution?->id,
            'students' => $institution?->studentDetails()->count(),
            'teachers' => $institution?->teacherDetails()->count(),
            'subjects' => $institution?->subjects()->count(),
        ];

        Log::info('Demo account reset', $summary);
        $this->info('Demo account reset. '.json_encode($summary));

        return self::SUCCESS;
    }
}
