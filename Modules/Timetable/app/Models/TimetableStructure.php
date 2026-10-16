<?php

namespace Modules\Timetable\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Institution\Models\Institution;

class TimetableStructure extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'name',
        'curriculum_type',
        'days_of_week',
        'periods_configuration',
        'is_default',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'periods_configuration' => 'array',
        'is_default' => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(TimetableDraft::class, 'structure_id');
    }

    /**
     * Return only lesson periods (excluding breaks, lunch, assemblies).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTeachingPeriods(): array
    {
        $periods = $this->periods_configuration ?? [];

        return array_values(array_filter($periods, function ($item) {
            return empty($item['is_break']);
        }));
    }

    /**
     * Return all schedule intervals (including breaks with color tokens)
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllScheduleIntervals(): array
    {
        return $this->periods_configuration ?? $this->grid_template ?? [];
    }

    /**
     * Dynamically build a custom bell schedule configuration from user answers,
     * supporting an arbitrary list of breaks positioned after ANY lesson period.
     */
    public static function buildCustomConfiguration(array $answers): array
    {
        $startTimeStr = $answers['school_start_time'] ?? '08:00';
        $lessonMinutes = (int) ($answers['lesson_duration'] ?? 40);
        $totalPeriods = (int) ($answers['daily_periods'] ?? 8);

        $hasAssembly = ! empty($answers['has_assembly']);
        $assemblyDuration = (int) ($answers['assembly_duration'] ?? 30);

        // Custom breaks array: [ ['name' => 'Tea Break', 'after_period' => 2, 'duration' => 20, 'color' => 'amber'], ... ]
        $breaks = $answers['breaks'] ?? [];
        if (empty($breaks)) {
            // Fallback for legacy format if any
            if (! empty($answers['has_short_break'])) {
                $breaks[] = ['name' => 'Short Break', 'after_period' => (int) ($answers['short_break_after'] ?? 2), 'duration' => (int) ($answers['short_break_duration'] ?? 10), 'color' => 'sky'];
            }
            if (! empty($answers['has_tea_break'])) {
                $breaks[] = ['name' => 'Tea Break', 'after_period' => (int) ($answers['tea_break_after'] ?? 4), 'duration' => (int) ($answers['tea_break_duration'] ?? 30), 'color' => 'amber'];
            }
            if (! empty($answers['has_lunch'])) {
                $breaks[] = ['name' => 'Lunch Break', 'after_period' => (int) ($answers['lunch_after'] ?? 6), 'duration' => (int) ($answers['lunch_duration'] ?? 60), 'color' => 'emerald'];
            }
        }

        $hasGames = ! empty($answers['has_games']);
        $gamesDuration = (int) ($answers['games_duration'] ?? 45);

        $currentTime = Carbon::createFromFormat('H:i', $startTimeStr);
        $config = [];

        // Optional Assembly before Period 1
        if ($hasAssembly) {
            $end = $currentTime->copy()->addMinutes($assemblyDuration);
            $config[] = [
                'period_number' => 0,
                'name' => 'Morning Assembly',
                'start_time' => $currentTime->format('H:i'),
                'end_time' => $end->format('H:i'),
                'is_break' => true,
                'type' => 'assembly',
                'color' => 'indigo', // purple/indigo badge
            ];
            $currentTime = $end;
        }

        for ($p = 1; $p <= $totalPeriods; $p++) {
            // Lesson Period
            $end = $currentTime->copy()->addMinutes($lessonMinutes);
            $config[] = [
                'period_number' => $p,
                'name' => "Period {$p}",
                'start_time' => $currentTime->format('H:i'),
                'end_time' => $end->format('H:i'),
                'is_break' => false,
                'type' => 'lesson',
            ];
            $currentTime = $end;

            // Check if any break is scheduled after this period $p
            foreach ($breaks as $brk) {
                if ((int) ($brk['after_period'] ?? 0) === $p) {
                    $brkDuration = (int) ($brk['duration'] ?? 20);
                    $brkEnd = $currentTime->copy()->addMinutes($brkDuration);
                    $config[] = [
                        'period_number' => 0,
                        'name' => $brk['name'] ?? 'Break',
                        'start_time' => $currentTime->format('H:i'),
                        'end_time' => $brkEnd->format('H:i'),
                        'is_break' => true,
                        'type' => 'break',
                        'after_period' => $p,
                        'color' => $brk['color'] ?? 'amber',
                    ];
                    $currentTime = $brkEnd;
                }
            }
        }

        // Optional Evening Games / Clubs
        if ($hasGames) {
            $gamesEnd = $currentTime->copy()->addMinutes($gamesDuration);
            $config[] = [
                'period_number' => 0,
                'name' => 'Games / Clubs',
                'start_time' => $currentTime->format('H:i'),
                'end_time' => $gamesEnd->format('H:i'),
                'is_break' => true,
                'type' => 'activity',
                'color' => 'teal',
            ];
        }

        return $config;
    }

    /**
     * Generate standard Kenyan bell schedule configuration
     */
    public static function defaultPeriods(): array
    {
        return self::buildCustomConfiguration([
            'school_start_time' => '08:20',
            'lesson_duration' => 40,
            'daily_periods' => 8,
            'has_assembly' => false,
            'breaks' => [
                ['name' => 'Short Break', 'after_period' => 2, 'duration' => 10, 'color' => 'sky'],
                ['name' => 'Tea Break', 'after_period' => 4, 'duration' => 30, 'color' => 'amber'],
                ['name' => 'Lunch Break', 'after_period' => 6, 'duration' => 60, 'color' => 'emerald'],
            ],
            'has_games' => false,
            'games_duration' => 0,
        ]);
    }

    /**
     * Retrieve or instantiate the default structure for an institution
     */
    public static function getOrCreateDefault(int $institutionId, ?string $curriculumType = null): self
    {
        $existing = self::where('institution_id', $institutionId)
            ->when($curriculumType, fn ($q) => $q->where('curriculum_type', $curriculumType))
            ->where('is_default', true)
            ->first();

        if ($existing) {
            return $existing;
        }

        return self::create([
            'institution_id' => $institutionId,
            'name' => 'Kenyan Standard Bell Schedule',
            'curriculum_type' => $curriculumType,
            'days_of_week' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'periods_configuration' => self::defaultPeriods(),
            'is_default' => true,
        ]);
    }

    /**
     * Get the next consecutive teaching period if no break lies between them.
     * Returns null if followed by a break, lunch, or is the last period of the day.
     *
     * @return array<string, mixed>|null
     */
    public function getNextConsecutiveTeachingPeriod(string $timeStr): ?array
    {
        $intervals = $this->getAllScheduleIntervals();
        $targetIndex = null;

        foreach ($intervals as $index => $item) {
            $itemTime = $item['time'] ?? (($item['start_time'] ?? '').'-'.($item['end_time'] ?? ''));
            if ($itemTime === $timeStr) {
                $targetIndex = $index;
                break;
            }
        }

        if ($targetIndex === null) {
            return null;
        }

        $nextIndex = $targetIndex + 1;
        if (! isset($intervals[$nextIndex])) {
            return null;
        }

        $nextItem = $intervals[$nextIndex];

        // Break intervals strictly protect double periods from crossing rest/lunch
        if (! empty($nextItem['is_break'])) {
            return null;
        }

        if (! isset($nextItem['time'])) {
            $nextItem['time'] = ($nextItem['start_time'] ?? '').'-'.($nextItem['end_time'] ?? '');
        }
        if (! isset($nextItem['start_time']) && isset($nextItem['time'])) {
            $parts = explode('-', $nextItem['time']);
            $nextItem['start_time'] = $parts[0] ?? '';
            $nextItem['end_time'] = $parts[1] ?? '';
        }

        return $nextItem;
    }

    /**
     * Check if a period slot can form a double lesson with the subsequent period.
     */
    public function canFormDoublePeriod(string $timeStr): bool
    {
        return $this->getNextConsecutiveTeachingPeriod($timeStr) !== null;
    }

    /**
     * Check if two period numbers are consecutive teaching periods without an intervening break.
     */
    public function areConsecutiveTeachingPeriods(int $periodNumberA, int $periodNumberB): bool
    {
        if (abs($periodNumberA - $periodNumberB) !== 1 || min($periodNumberA, $periodNumberB) < 1) {
            return false;
        }

        $first = min($periodNumberA, $periodNumberB);
        $second = max($periodNumberA, $periodNumberB);

        // Adjacency is decided by the order of intervals, not by a stored marker,
        // so structures saved before 'after_period' existed are protected too.
        $intervals = array_values($this->getAllScheduleIntervals());

        foreach ($intervals as $index => $interval) {
            if (! empty($interval['is_break']) || (int) ($interval['period_number'] ?? 0) !== $first) {
                continue;
            }

            $next = $intervals[$index + 1] ?? null;

            return $next !== null
                && empty($next['is_break'])
                && (int) ($next['period_number'] ?? 0) === $second;
        }

        return false;
    }
}
