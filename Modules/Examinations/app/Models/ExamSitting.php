<?php

namespace Modules\Examinations\Models;

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
 */
class ExamSitting extends Model
{
    use HasFactory;

    /** Shared with the create form's dropdown; see {@see Examination::EXAM_TYPES}. */
    public const EXAM_TYPES = Examination::EXAM_TYPES;

    protected $fillable = [
        'institution_id',
        'title',
        'term',
        'exam_type',
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
     * The class this sitting is targeted at, or null when it runs for every
     * class in the institution.
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
