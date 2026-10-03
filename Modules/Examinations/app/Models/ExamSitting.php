<?php

namespace Modules\Examinations\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;

/**
 * An examination sitting: the event a school runs - "Mock Exam",
 * "End Term" - for a term. It comprises one paper per subject per class
 * (the {@see Examination} rows hanging off it), created in one go rather
 * than a subject at a time.
 *
 * @property int $id
 * @property int $institution_id
 * @property string $title
 * @property string|null $term
 * @property string|null $exam_type
 * @property int|null $academic_year
 * @property int|null $class_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ExamSitting extends Model
{
    use HasFactory;

    /** Shared with the create form's dropdown; see {@see Examination::EXAM_TYPES}. */
    public const EXAM_TYPES = Examination::EXAM_TYPES;

    /**
     * What kind of assessment event a sitting is.
     *
     * Null on existing rows means summative — isSummative() returns true
     * for null so nothing that predates the column breaks.
     *
     * @var array<string, string>
     */
    public const ASSESSMENT_CATEGORIES = [
        'summative' => 'Summative Exam',
        'classroom_assessment' => 'Classroom Assessment (CA)',
        'sba' => 'School-Based Assessment (SBA)',
        'national' => 'National Assessment',
    ];

    protected $fillable = [
        'institution_id',
        'title',
        'term',
        'exam_type',
        'assessment_category',
        'academic_year',
        'class_id',
        'notes',
    ];

    protected $casts = [
        'academic_year' => 'integer',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * The papers that make up this sitting - one per subject per class.
     */
    public function papers(): HasMany
    {
        return $this->hasMany(Examination::class, 'sitting_id');
    }

    public function examTypeLabel(): ?string
    {
        return $this->exam_type
            ? (self::EXAM_TYPES[$this->exam_type] ?? ucwords(str_replace('_', ' ', $this->exam_type)))
            : null;
    }

    public function assessmentCategoryLabel(): string
    {
        return self::ASSESSMENT_CATEGORIES[$this->assessment_category ?? 'summative']
            ?? ucwords(str_replace('_', ' ', (string) $this->assessment_category));
    }

    /**
     * A sitting with no category set predates the column and is treated
     * as summative — the only kind that existed before CBC support.
     */
    public function isSummative(): bool
    {
        return $this->assessment_category === null
            || $this->assessment_category === 'summative';
    }

    public function isClassroomAssessment(): bool
    {
        return $this->assessment_category === 'classroom_assessment';
    }

    public function isSba(): bool
    {
        return $this->assessment_category === 'sba';
    }

    public function isNational(): bool
    {
        return $this->assessment_category === 'national';
    }

    /**
     * Any formative sitting: either a teacher-designed classroom task
     * or an SBA tool. Both use direct band entry instead of marks.
     */
    public function isFormative(): bool
    {
        return $this->isClassroomAssessment() || $this->isSba();
    }

    /**
     * How the sitting reads in a list - the name the school gave it
     * ("Mock Exam", "End Term Exam"), falling back to the type label or
     * term for rows that predate a stored title.
     */
    public function displayName(): string
    {
        return $this->title ?: ($this->examTypeLabel() ?? $this->term ?? 'Examination');
    }

    /**
     * "Term 2 · 2026" - the period line shown under the name.
     */
    public function periodLabel(): string
    {
        return collect([$this->term, $this->academic_year])
            ->filter()
            ->implode(' · ') ?: 'No term set';
    }
}
