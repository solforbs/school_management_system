<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ParentMatchingService;
use App\Services\StudentCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;

class MergeDuplicateStudents extends Command
{
    protected $signature = 'students:merge-duplicates 
                            {--institution= : Limit merging to a specific institution ID}
                            {--class= : Limit merging to a specific class name or ID (e.g. "PP2")}
                            {--dry-run : Simulate the merge without making changes to the database}
                            {--audit : Output detailed duplicate reconciliation report without making changes}';

    protected $description = 'Detect and merge duplicate student accounts sharing the same name or admission number within an institution.';

    public function handle(): int
    {
        $institutionId = $this->option('institution');
        $classFilter = $this->option('class');
        $isDryRun = (bool) $this->option('dry-run') || (bool) $this->option('audit');
        $isAudit = (bool) $this->option('audit');

        if ($isDryRun) {
            $this->warn('Running in '.($isAudit ? 'AUDIT' : 'DRY-RUN').' mode. No changes will be committed to the database.');
        }

        $institutions = Institution::query()
            ->when($institutionId, fn ($q, $id) => $q->where('id', $id))
            ->get();

        if ($institutions->isEmpty()) {
            $this->error('No institutions found matching the criteria.');

            return self::FAILURE;
        }

        $totalMerged = 0;
        $totalReassigned = 0;
        $auditMappings = [];

        foreach ($institutions as $institution) {
            $this->line("Processing institution: {$institution->name} (ID: {$institution->id})...");

            $classIdFilter = null;
            if ($classFilter) {
                $targetClass = SchoolClass::where('institution_id', $institution->id)
                    ->where(function ($q) use ($classFilter) {
                        $q->where('name', $classFilter)
                            ->orWhere('id', $classFilter);
                    })
                    ->first();

                if (! $targetClass) {
                    $this->warn("Class '{$classFilter}' not found for institution {$institution->name} (ID: {$institution->id}). Skipping class filter.");
                } else {
                    $classIdFilter = $targetClass->id;
                    $this->info("Filtering merge strictly to class: {$targetClass->name} (ID: {$targetClass->id})");
                }
            }

            $affectedClassIds = [];

            $allStudents = StudentDetails::where('institution_id', $institution->id)
                ->when($classIdFilter, fn ($q) => $q->where('class_id', $classIdFilter))
                ->with(['student', 'schoolClass'])
                ->get();

            // Group by normalized student name across the institution
            $groupedByName = $allStudents
                ->filter(fn (StudentDetails $details) => ! empty(trim((string) ($details->student->name ?? ''))))
                ->groupBy(fn (StudentDetails $details): string => (string) preg_replace('/\s+/', ' ', strtolower(trim((string) ($details->student->name ?? '')))))
                ->filter(fn ($group) => $group->count() > 1);

            foreach ($groupedByName as $groupKey => $records) {
                // Partition group into clusters of duplicates sharing admission number, class, or parent
                $clusters = $this->clusterDuplicateRecords($records);

                foreach ($clusters as $cluster) {
                    if ($cluster->count() <= 1) {
                        continue;
                    }

                    // Determine best keeper record
                    $sorted = $cluster->sort(function (StudentDetails $a, StudentDetails $b) {
                        $scoreA = $this->calculateKeeperScore($a);
                        $scoreB = $this->calculateKeeperScore($b);

                        if ($scoreA !== $scoreB) {
                            return $scoreB <=> $scoreA; // higher score first
                        }

                        return $a->id <=> $b->id; // earliest first
                    })->values();

                    /** @var StudentDetails $keeper */
                    $keeper = $sorted->first();
                    $duplicates = $sorted->slice(1);

                    $studentName = $keeper->student->name ?? 'Unknown';
                    $this->info("Duplicate cluster for '{$studentName}': Keeper ID #{$keeper->student_id} (Details #{$keeper->id}, ADM: {$keeper->admission_number}), Duplicates: [{$duplicates->pluck('student_id')->join(', ')}]");

                    $clusterRows = [];
                    foreach ($sorted as $rec) {
                        $isKeeperRec = $rec->id === $keeper->id;
                        $resultsCount = DB::getSchemaBuilder()->hasTable('results') ? DB::table('results')->where('student_id', $rec->student_id)->count() : 0;
                        $attendanceCount = DB::getSchemaBuilder()->hasTable('attendances') ? DB::table('attendances')->where('student_id', $rec->student_id)->count() : 0;
                        $feesCount = DB::getSchemaBuilder()->hasTable('fees') ? DB::table('fees')->where('student_id', $rec->student_id)->count() : 0;

                        $clusterRows[] = [
                            $rec->student->name ?? 'Unknown',
                            (string) $rec->student_id,
                            (string) $rec->id,
                            $rec->admission_number ?? 'NULL',
                            $rec->schoolClass->name ?? ($rec->class_id ? "ID {$rec->class_id}" : 'Unassigned'),
                            (string) $resultsCount,
                            (string) $attendanceCount,
                            (string) $feesCount,
                            $rec->guardian_name ?? ($rec->parent_id ? "Parent #{$rec->parent_id}" : 'None'),
                            (string) $this->calculateKeeperScore($rec),
                            $isKeeperRec ? 'RETAIN (KEEPER)' : 'MERGE & DEACTIVATE',
                        ];
                    }

                    $this->table(
                        ['Name', 'User ID', 'Details ID', 'Adm No', 'Class', 'Results', 'Attendance', 'Fees', 'Guardian', 'Score', 'Decision'],
                        $clusterRows
                    );

                    foreach ($duplicates as $dup) {
                        $auditMappings[] = [
                            'Student Name' => $studentName,
                            'Duplicate User ID' => $dup->student_id,
                            'Duplicate Adm' => $dup->admission_number ?? 'NULL',
                            'Retained User ID' => $keeper->student_id,
                            'Retained Adm' => $keeper->admission_number ?? 'NULL',
                            'Action' => 'Reassign records & deactive duplicate',
                        ];
                    }

                    if ($isDryRun) {
                        $totalMerged += $duplicates->count();

                        continue;
                    }

                    DB::transaction(function () use ($keeper, $duplicates, &$totalMerged, &$totalReassigned, &$affectedClassIds) {
                        if ($keeper->class_id) {
                            $affectedClassIds[(int) $keeper->class_id] = true;
                        }

                        foreach ($duplicates as $dup) {
                            /** @var StudentDetails $dup */
                            $dupStudentId = $dup->student_id;
                            $keeperStudentId = $keeper->student_id;

                            if ($dup->class_id) {
                                $affectedClassIds[(int) $dup->class_id] = true;
                            }

                            // 1. Inherit missing or better details from duplicate to keeper
                            if ($this->isAutoGeneratedAdm($keeper->admission_number) && ! $this->isAutoGeneratedAdm($dup->admission_number) && ! empty($dup->admission_number)) {
                                $keeper->admission_number = $dup->admission_number;
                            } elseif (empty($keeper->admission_number) && ! empty($dup->admission_number)) {
                                $keeper->admission_number = $dup->admission_number;
                            }

                            if (empty($keeper->class_id) && ! empty($dup->class_id)) {
                                $keeper->class_id = $dup->class_id;
                            }
                            if (empty($keeper->student_number) && ! empty($dup->student_number)) {
                                $keeper->student_number = $dup->student_number;
                            }
                            if (empty($keeper->gender) && ! empty($dup->gender)) {
                                $keeper->gender = $dup->gender;
                            }
                            if (empty($keeper->parent_id) && ! empty($dup->parent_id)) {
                                $keeper->parent_id = $dup->parent_id;
                            }
                            if (empty($keeper->guardian_name) && ! empty($dup->guardian_name)) {
                                $keeper->guardian_name = $dup->guardian_name;
                            }
                            if (empty($keeper->guardian_phone) && ! empty($dup->guardian_phone)) {
                                $keeper->guardian_phone = $dup->guardian_phone;
                            }

                            // 2. Reassign related records
                            if ($dupStudentId && $keeperStudentId && $dupStudentId !== $keeperStudentId) {
                                $totalReassigned += $this->reassignStudentRelations($dupStudentId, $keeperStudentId);
                            }

                            // Clear admission number on duplicate to prevent unique constraint conflict
                            $dup->admission_number = null;
                            $dup->save();

                            // 3. Soft-delete duplicate student_details and delete duplicate user
                            $dup->delete();
                            if ($dupStudentId && $dupStudentId !== $keeperStudentId) {
                                User::where('id', $dupStudentId)->delete();
                            }

                            $totalMerged++;
                        }

                        $keeper->save();
                    });
                }
            }

            if (! $isDryRun) {
                $cache = app(StudentCacheService::class);
                foreach (array_keys($affectedClassIds) as $cId) {
                    $cache->invalidateClass($institution->id, $cId);
                }
                $cache->invalidateInstitution($institution->id);
            }
        }

        $this->newLine();
        if (! empty($auditMappings)) {
            $this->info('Proposed Duplicate -> Retained Mapping Summary:');
            $this->table(
                ['Student Name', 'Duplicate User ID', 'Duplicate Adm', 'Retained User ID', 'Retained Adm', 'Action'],
                $auditMappings
            );
        }

        if ($isDryRun) {
            $this->info("Dry run complete: {$totalMerged} duplicate student record(s) would be merged.");
        } else {
            $this->info("Successfully merged {$totalMerged} duplicate student record(s) and reassigned {$totalReassigned} related record(s).");
        }

        return self::SUCCESS;
    }

    /**
     * Partition duplicate name records into cohesive clusters sharing admission numbers, class, or parent.
     *
     * @param  Collection<int, StudentDetails>  $records
     * @return array<int, Collection<int, StudentDetails>>
     */
    private function clusterDuplicateRecords(Collection $records): array
    {
        /** @var array<int, Collection<int, StudentDetails>> $clusters */
        $clusters = [];

        foreach ($records as $record) {
            $placed = false;

            foreach ($clusters as $key => $cluster) {
                if ($this->shouldBelongToCluster($record, $cluster)) {
                    $clusters[$key]->push($record);
                    $placed = true;
                    break;
                }
            }

            if (! $placed) {
                $clusters[] = collect([$record]);
            }
        }

        return $clusters;
    }

    /**
     * @param  Collection<int, StudentDetails>  $cluster
     */
    private function shouldBelongToCluster(StudentDetails $candidate, Collection $cluster): bool
    {
        $matcher = app(ParentMatchingService::class);
        $candPhone = $candidate->guardian_phone ? $matcher->normalizePhone($candidate->guardian_phone) : null;

        foreach ($cluster as $member) {
            // 1. If explicit dates of birth are present and different, they are different people
            if ($candidate->date_of_birth && $member->date_of_birth && $candidate->date_of_birth !== $member->date_of_birth) {
                return false;
            }

            // 2. If explicit guardian phones are present and different, different people
            $memPhone = $member->guardian_phone ? $matcher->normalizePhone($member->guardian_phone) : null;
            if ($candPhone && $memPhone && $candPhone !== $memPhone) {
                return false;
            }

            // 3. If explicit classes are present and differ, and both have different parents/phones, different people
            if ($candidate->class_id && $member->class_id && (int) $candidate->class_id !== (int) $member->class_id && ($candPhone || $memPhone)) {
                return false;
            }

            // Shared parent link
            if ($candidate->parent_id && $member->parent_id && (int) $candidate->parent_id === (int) $member->parent_id) {
                return true;
            }

            // Shared guardian phone
            if ($candPhone && $memPhone && $candPhone === $memPhone) {
                return true;
            }

            // Shared guardian name
            if ($candidate->guardian_name && $member->guardian_name && strcasecmp(trim($candidate->guardian_name), trim($member->guardian_name)) === 0) {
                return true;
            }

            // Created within same day / 24 hours of each other (e.g. repeated bulk upload passes)
            if ($candidate->created_at && $member->created_at && abs($candidate->created_at->diffInHours($member->created_at)) <= 24) {
                return true;
            }

            // Same or unassigned class with no conflicting identity data
            if (empty($candidate->class_id) || empty($member->class_id) || (int) $candidate->class_id === (int) $member->class_id) {
                return true;
            }
        }

        return false;
    }

    private function isAutoGeneratedAdm(?string $adm): bool
    {
        if (empty($adm)) {
            return false;
        }

        return (bool) preg_match('/^ADM-\d{4}-[A-Z0-9]{6}$/i', trim($adm));
    }

    private function calculateKeeperScore(StudentDetails $details): int
    {
        $score = 0;

        // Human-assigned admission number scores highest
        if (! empty($details->admission_number)) {
            $score += $this->isAutoGeneratedAdm($details->admission_number) ? 10 : 100;
        }

        if (! empty($details->student_number)) {
            $score += 15;
        }

        if (! empty($details->class_id)) {
            $score += 25;
        }

        if (! empty($details->parent_id)) {
            $score += 10;
        }

        if (! empty($details->gender)) {
            $score += 5;
        }

        if (! empty($details->guardian_phone)) {
            $score += 5;
        }

        $studentId = $details->student_id;
        if ($studentId) {
            if (DB::getSchemaBuilder()->hasTable('results')) {
                $score += DB::table('results')->where('student_id', $studentId)->count() * 10;
            }
            if (DB::getSchemaBuilder()->hasTable('attendances')) {
                $score += DB::table('attendances')->where('student_id', $studentId)->count() * 5;
            }
            if (DB::getSchemaBuilder()->hasTable('fees')) {
                $score += DB::table('fees')->where('student_id', $studentId)->count() * 5;
            }
        }

        return $score;
    }

    private function reassignStudentRelations(int $fromStudentId, int $toStudentId): int
    {
        $reassigned = 0;

        // Results
        if (DB::getSchemaBuilder()->hasTable('results')) {
            $existingResultCombos = DB::table('results')
                ->where('student_id', $toStudentId)
                ->pluck('examination_id')
                ->all();

            DB::table('results')
                ->where('student_id', $fromStudentId)
                ->whereIn('examination_id', $existingResultCombos)
                ->delete();

            $reassigned += DB::table('results')
                ->where('student_id', $fromStudentId)
                ->update(['student_id' => $toStudentId]);
        }

        // Attendances
        if (DB::getSchemaBuilder()->hasTable('attendances')) {
            $existingDates = DB::table('attendances')
                ->where('student_id', $toStudentId)
                ->pluck('date')
                ->all();

            DB::table('attendances')
                ->where('student_id', $fromStudentId)
                ->whereIn('date', $existingDates)
                ->delete();

            $reassigned += DB::table('attendances')
                ->where('student_id', $fromStudentId)
                ->update(['student_id' => $toStudentId]);
        }

        // Student Subject Selections
        if (DB::getSchemaBuilder()->hasTable('student_subject_selections')) {
            $existingSubjects = DB::table('student_subject_selections')
                ->where('student_id', $toStudentId)
                ->pluck('subject_id')
                ->all();

            DB::table('student_subject_selections')
                ->where('student_id', $fromStudentId)
                ->whereIn('subject_id', $existingSubjects)
                ->delete();

            $reassigned += DB::table('student_subject_selections')
                ->where('student_id', $fromStudentId)
                ->update(['student_id' => $toStudentId]);
        }

        // Fees and Payments
        if (DB::getSchemaBuilder()->hasTable('fees')) {
            $reassigned += DB::table('fees')
                ->where('student_id', $fromStudentId)
                ->update(['student_id' => $toStudentId]);
        }
        if (DB::getSchemaBuilder()->hasTable('fee_payments')) {
            $reassigned += DB::table('fee_payments')
                ->where('student_id', $fromStudentId)
                ->update(['student_id' => $toStudentId]);
        }

        // ZKTeco Attendance Logs (pin)
        if (DB::getSchemaBuilder()->hasTable('zkteco_attendance_logs')) {
            DB::table('zkteco_attendance_logs')
                ->where('pin', (string) $fromStudentId)
                ->update(['pin' => (string) $toStudentId]);
        }

        // Report Cards
        if (DB::getSchemaBuilder()->hasTable('report_cards')) {
            $existingReportCardTerms = DB::table('report_cards')
                ->where('student_id', $toStudentId)
                ->pluck('term')
                ->all();

            DB::table('report_cards')
                ->where('student_id', $fromStudentId)
                ->whereIn('term', $existingReportCardTerms)
                ->delete();

            $reassigned += DB::table('report_cards')
                ->where('student_id', $fromStudentId)
                ->update(['student_id' => $toStudentId]);
        }

        return $reassigned;
    }
}
