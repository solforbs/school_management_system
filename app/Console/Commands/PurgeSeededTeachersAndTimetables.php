<?php

namespace App\Console\Commands;

use App\Models\TeacherClasses;
use App\Models\TeacherSubjects;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Institution\Models\Institution;
use Modules\Teacher\Models\TeacherDetails;
use Modules\Timetable\Models\TimetableEntry;

class PurgeSeededTeachersAndTimetables extends Command
{
    protected $signature = 'school:purge-seeded-data 
                            {--institution= : Institution ID, code, or name to clean}
                            {--all : Clean seeded test teachers across all institutions}
                            {--timetables : Also purge all timetable entries for the targeted institution(s)}
                            {--verify-teachers : Explicitly verify email for legitimate teachers}
                            {--dry-run : Preview changes without deleting}';

    protected $description = 'Purge unwanted seeded test teachers (@school.test) and timetable entries, and auto-verify real teachers.';

    public function handle(): int
    {
        $institutionArg = $this->option('institution');
        $all = (bool) $this->option('all');
        $clearTimetables = (bool) $this->option('timetables');
        $dryRun = (bool) $this->option('dry-run');

        if (! $institutionArg && ! $all) {
            $this->error('Please specify --institution=ID/CODE or --all to proceed.');
            $this->info('Available institutions:');
            foreach (Institution::all(['id', 'name', 'code']) as $inst) {
                $this->line("  [ID: {$inst->id}] {$inst->name} (Code: {$inst->code})");
            }

            return self::FAILURE;
        }

        $institutions = $all
            ? Institution::all()
            : Institution::where('id', $institutionArg)
                ->orWhere('code', $institutionArg)
                ->orWhere('name', 'like', "%{$institutionArg}%")
                ->get();

        if ($institutions->isEmpty()) {
            $this->error("No institution found matching '{$institutionArg}'.");

            return self::FAILURE;
        }

        foreach ($institutions as $institution) {
            $this->info('=================================================');
            $this->info("Processing Institution: {$institution->name} (ID: {$institution->id}, Code: {$institution->code})");
            $this->info('=================================================');

            // 1. Identify test teachers
            $testTeacherDetails = TeacherDetails::where('institution_id', $institution->id)
                ->whereHas('teacher', function ($q) {
                    $q->where('email', 'like', '%@school.test');
                })
                ->with('teacher')
                ->get();

            $this->line("Found {$testTeacherDetails->count()} seeded test teachers (@school.test).");

            if ($testTeacherDetails->isNotEmpty()) {
                if ($dryRun) {
                    $this->warn("[DRY RUN] Would delete {$testTeacherDetails->count()} test teachers.");
                } else {
                    $deletedTeachersCount = 0;
                    DB::transaction(function () use ($testTeacherDetails, &$deletedTeachersCount) {
                        foreach ($testTeacherDetails as $details) {
                            $user = $details->teacher;
                            $userId = $details->teacher_id;

                            // Remove assigned classes and subjects
                            TeacherClasses::where('teacher_id', $userId)->delete();
                            TeacherSubjects::where('teacher_id', $userId)->delete();

                            // Also delete any timetable entries assigned to this test teacher
                            TimetableEntry::where('teacher_id', $userId)->delete();

                            $details->delete();
                            if ($user && str_ends_with($user->email, '@school.test')) {
                                $user->delete();
                            }

                            $deletedTeachersCount++;
                        }
                    });

                    $this->info("✓ Successfully purged {$deletedTeachersCount} seeded test teachers and their assignments.");
                }
            }

            // 2. Timetable purge (if requested)
            if ($clearTimetables) {
                $timetableCount = TimetableEntry::where('institution_id', $institution->id)->count();
                if ($timetableCount > 0) {
                    if ($dryRun) {
                        $this->warn("[DRY RUN] Would delete {$timetableCount} timetable entries.");
                    } else {
                        TimetableEntry::where('institution_id', $institution->id)->delete();
                        $this->info("✓ Successfully deleted {$timetableCount} timetable entries for {$institution->name}.");
                    }
                } else {
                    $this->line('No timetable entries to delete.');
                }
            }

            // 3. Auto-verify legitimate teachers (only if explicitly requested)
            if ($this->option('verify-teachers')) {
                $realTeacherDetails = TeacherDetails::where('institution_id', $institution->id)
                    ->whereHas('teacher', function ($q) {
                        $q->where('email', 'not like', '%@school.test');
                    })
                    ->with('teacher')
                    ->get();

                $verifiedCount = 0;
                foreach ($realTeacherDetails as $details) {
                    $user = $details->teacher;
                    if ($user && ! $user->email_verified_at) {
                        if ($dryRun) {
                            $this->line("[DRY RUN] Would verify email for: {$user->name} ({$user->email})");
                        } else {
                            $user->forceFill(['email_verified_at' => now()])->save();
                            $this->info("✓ Verified email for teacher: {$user->name} ({$user->email})");
                            $verifiedCount++;
                        }
                    }
                }

                if ($verifiedCount === 0 && ! $dryRun) {
                    $this->line('All legitimate teachers already have verified emails.');
                }
            }
        }

        $this->newLine();
        $this->info('All requested cleanup tasks completed successfully!');

        return self::SUCCESS;
    }
}
