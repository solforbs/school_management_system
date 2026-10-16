<?php

namespace Modules\Timetable\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Classes\Models\SchoolClass;
use Modules\Curriculum\Models\Curriculum;
use Modules\Institution\Models\Institution;
use Modules\Subject\Models\Subject;

class SubjectAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'curriculum_id',
        'class_id',
        'subject_id',
        'periods_per_week',
        'max_per_day',
        'allow_double_periods',
        'cognitive_weight',
    ];

    protected $casts = [
        'periods_per_week' => 'integer',
        'max_per_day' => 'integer',
        'allow_double_periods' => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get numeric weight for sorting most cognitively demanding subjects first.
     */
    public function getWeightScore(): int
    {
        return match ($this->cognitive_weight) {
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 2,
        };
    }

    /**
     * Whether a subject name matches any keyword in the list. Whole-word,
     * case-insensitive; a trailing "*" matches any word starting with the
     * keyword. See config/timetable.php.
     *
     * @param  array<int, string>  $keywords
     */
    public static function nameMatches(string $name, array $keywords): bool
    {
        $normalized = strtolower(trim($name));

        foreach ($keywords as $keyword) {
            $keyword = strtolower(trim($keyword));
            if ($keyword === '') {
                continue;
            }

            $prefix = str_ends_with($keyword, '*');
            $keyword = rtrim($keyword, '*');
            $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($keyword, '/').($prefix ? '[\p{L}\p{N}]*' : '(?![\p{L}\p{N}])').'/u';

            if (preg_match($pattern, $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Infer pedagogical cognitive load weight based on subject title
     */
    public static function inferWeight(string $name): string
    {
        if (self::nameMatches($name, config('timetable.weights.high', []))) {
            return 'high';
        }

        if (self::nameMatches($name, config('timetable.weights.low', []))) {
            return 'low';
        }

        return config('timetable.fallback_cognitive_weight', 'medium');
    }

    /**
     * Infer typical weekly quota if the school has not configured one.
     * Callers should treat this as a fallback and tell the school about it.
     */
    public static function inferDefaultPeriods(string $name, ?string $system = 'cbc'): int
    {
        foreach (config('timetable.default_periods', []) as $periods => $keywords) {
            if (self::nameMatches($name, $keywords)) {
                return (int) $periods;
            }
        }

        return (int) config('timetable.fallback_periods_per_week', 3);
    }

    /**
     * Whether a subject with no explicit allocation should be offered
     * double lessons, judged by name and weekly quota.
     */
    public static function inferAllowDouble(string $name, int $periodsPerWeek): bool
    {
        return $periodsPerWeek >= (int) config('timetable.double_min_periods', 4)
            && self::nameMatches($name, config('timetable.double_lesson_subjects', []));
    }
}
