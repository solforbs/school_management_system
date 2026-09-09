<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Institution\Database\Seeders\DemoAccountSeeder;
use Modules\Institution\Models\Institution;

/**
 * Wipes the shared demo school and rebuilds it from the seeders, so
 * whatever onboarding directors changed while poking around is gone. Runs
 * on a schedule (see routes/console.php) and is also how the demo is
 * created the first time.
 */
class ResetDemoAccount extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Reset the shared demo school to its seeded state';

    /**
     * Every table the demo seeders write that is scoped by `institution_id`.
     * Cleared explicitly with FK checks off, because several of them
     * (`results`, `expenditures`, `staff_payments`) are reachable from an
     * institution delete by more than one cascade path, which InnoDB
     * refuses.
     *
     * @var list<string>
     */
    private const SCOPED_TABLES = [
        'results', 'examinations', 'exam_sittings', 'report_cards',
        'lessons', 'lesson_reports', 'subject_selections',
        'expenditures', 'expenditure_categories', 'staff_payments', 'staff_details',
        'fees', 'timetable_entries', 'subject_teachers', 'teacher_details',
        'student_details', 'grading_bands', 'report_templates', 'curricula',
        'classes', 'subjects', 'devices',
    ];

    public function handle(): int
    {
        DB::transaction(function () {
            Institution::demo()->pluck('id')->each(function (int $id) {
                Schema::withoutForeignKeyConstraints(function () use ($id) {
                    foreach (self::SCOPED_TABLES as $table) {
                        if (Schema::hasTable($table) && Schema::hasColumn($table, 'institution_id')) {
                            DB::table($table)->where('institution_id', $id)->delete();
                        }
                    }

                    DB::table('institutions')->where('id', $id)->delete();
                });
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
            'results' => $institution?->results()->count(),
        ];

        Log::info('Demo account reset', $summary);
        $this->info('Demo account reset. '.json_encode($summary));

        return self::SUCCESS;
    }
}
