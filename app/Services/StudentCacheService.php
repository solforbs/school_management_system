<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Classes\Models\SchoolClass;
use Modules\Student\Models\StudentDetails;

class StudentCacheService
{
    public const STATS_TTL = 1800; // 30 minutes

    public const CLASSES_TTL = 3600; // 1 hour

    /**
     * Get aggregated class headcount stats with single-query efficiency and caching.
     *
     * @return array{total: int, male: int, female: int, latest_admission: string, school_latest_admission: string}
     */
    public function getClassStats(int $institutionId, int $classId): array
    {
        $cacheKey = "inst_{$institutionId}_class_{$classId}_stats";

        return Cache::remember($cacheKey, self::STATS_TTL, function () use ($institutionId, $classId) {
            $row = DB::table('student_details')
                ->where('institution_id', $institutionId)
                ->where('class_id', $classId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN LOWER(gender) = 'male' THEN 1 ELSE 0 END) as male,
                    SUM(CASE WHEN LOWER(gender) = 'female' THEN 1 ELSE 0 END) as female
                ")
                ->first();

            $classAdmissions = DB::table('student_details')
                ->where('institution_id', $institutionId)
                ->where('class_id', $classId)
                ->whereNotNull('admission_number')
                ->where('admission_number', '!=', '')
                ->whereNull('deleted_at')
                ->pluck('admission_number');

            $latestAdmission = app(AdmissionNumberPatternService::class)->findHighestAdmission($classAdmissions) ?? 'None';

            $schoolLatest = $this->getSchoolLatestAdmission($institutionId);

            return [
                'total' => $row ? (int) $row->total : 0,
                'male' => $row ? (int) $row->male : 0,
                'female' => $row ? (int) $row->female : 0,
                'latest_admission' => $latestAdmission,
                'school_latest_admission' => $schoolLatest,
            ];
        });
    }

    /**
     * Institution-wide headcount stats (the "All / Mixed Classes" view).
     *
     * @return array{total: int, male: int, female: int, latest_admission: string, school_latest_admission: string}
     */
    public function getInstitutionStats(int $institutionId): array
    {
        return Cache::remember("inst_{$institutionId}_all_stats", self::STATS_TTL, function () use ($institutionId) {
            $row = DB::table('student_details')
                ->where('institution_id', $institutionId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN LOWER(gender) = 'male' THEN 1 ELSE 0 END) as male,
                    SUM(CASE WHEN LOWER(gender) = 'female' THEN 1 ELSE 0 END) as female
                ")
                ->first();

            $latest = $this->getSchoolLatestAdmission($institutionId);

            return [
                'total' => $row ? (int) $row->total : 0,
                'male' => $row ? (int) $row->male : 0,
                'female' => $row ? (int) $row->female : 0,
                'latest_admission' => $latest,
                'school_latest_admission' => $latest,
            ];
        });
    }

    /**
     * Cached roster of every student in the institution (capped for safety).
     *
     * @return Collection<int, StudentDetails>
     */
    public function getInstitutionRoster(int $institutionId, int $limit = 1000): Collection
    {
        $cacheKey = "inst_{$institutionId}_all_roster";

        $cached = Cache::get($cacheKey);
        if ($cached instanceof Collection) {
            return $cached;
        }

        if ($cached !== null) {
            Cache::forget($cacheKey);
        }

        $roster = StudentDetails::query()
            ->where('institution_id', $institutionId)
            ->with('student')
            ->orderBy('admission_number')
            ->limit($limit)
            ->get();

        Cache::put($cacheKey, $roster, self::STATS_TTL);

        return $roster;
    }

    /**
     * Get the latest admission number recorded for the institution using pattern detection.
     */
    public function getSchoolLatestAdmission(int $institutionId): string
    {
        $cacheKey = "inst_{$institutionId}_latest_adm";

        return Cache::remember($cacheKey, self::STATS_TTL, function () use ($institutionId) {
            $patternInfo = app(AdmissionNumberPatternService::class)->detectPattern($institutionId);
            $pred = $patternInfo['predominant_pattern'];

            if ($pred && ! empty($pred['highest_admission']) && $pred['highest_admission'] !== 'None') {
                return $pred['highest_admission'];
            }

            $allAdmissions = DB::table('student_details')
                ->where('institution_id', $institutionId)
                ->whereNotNull('admission_number')
                ->where('admission_number', '!=', '')
                ->whereNull('deleted_at')
                ->pluck('admission_number');

            return app(AdmissionNumberPatternService::class)->findHighestAdmission($allAdmissions) ?? 'None';
        });
    }

    /**
     * Get cached admission number pattern information for an institution.
     *
     * @return array<string, mixed>
     */
    public function getAdmissionPattern(int $institutionId): array
    {
        $cacheKey = "inst_{$institutionId}_adm_pattern";

        return Cache::remember($cacheKey, self::STATS_TTL, function () use ($institutionId) {
            return app(AdmissionNumberPatternService::class)->detectPattern($institutionId);
        });
    }

    /**
     * Get cached classes list for an institution.
     *
     * @return Collection<int, SchoolClass>
     */
    public function getClasses(int $institutionId, bool $isAdmin = false): Collection
    {
        $cacheKey = "inst_{$institutionId}_classes_".($isAdmin ? 'admin' : 'tenant');

        $cached = Cache::get($cacheKey);
        if ($cached instanceof Collection) {
            return $cached;
        }

        if ($cached !== null) {
            Cache::forget($cacheKey);
        }

        $classes = SchoolClass::when(! $isAdmin, fn ($q) => $q->where('institution_id', $institutionId))
            ->orderBy('name')
            ->get();

        Cache::put($cacheKey, $classes, self::CLASSES_TTL);

        return $classes;
    }

    /**
     * Get cached active student count for an institution (used for plan limit badges).
     */
    public function getInstitutionStudentCount(int $institutionId): int
    {
        $cacheKey = "inst_{$institutionId}_student_count";

        return Cache::remember($cacheKey, self::STATS_TTL, function () use ($institutionId) {
            return StudentDetails::where('institution_id', $institutionId)
                ->where('is_active', true)
                ->count();
        });
    }

    /**
     * Invalidate caches for a specific class (stats & roster) and update school totals.
     */
    public function invalidateClass(int $institutionId, ?int $classId = null): void
    {
        if ($classId !== null) {
            Cache::forget("inst_{$institutionId}_class_{$classId}_stats");
            Cache::forget("inst_{$institutionId}_class_{$classId}_roster");
        }

        Cache::forget("inst_{$institutionId}_student_count");
        Cache::forget("inst_{$institutionId}_latest_adm");
        Cache::forget("inst_{$institutionId}_adm_pattern");
        Cache::forget("inst_{$institutionId}_all_stats");
        Cache::forget("inst_{$institutionId}_all_roster");
    }

    /**
     * Invalidate institution-wide caches (classes, counts, admission sequences).
     */
    public function invalidateInstitution(int $institutionId): void
    {
        Cache::forget("inst_{$institutionId}_classes_admin");
        Cache::forget("inst_{$institutionId}_classes_tenant");
        Cache::forget("inst_{$institutionId}_student_count");
        Cache::forget("inst_{$institutionId}_latest_adm");
        Cache::forget("inst_{$institutionId}_adm_pattern");
        Cache::forget("inst_{$institutionId}_all_stats");
        Cache::forget("inst_{$institutionId}_all_roster");
    }
}
