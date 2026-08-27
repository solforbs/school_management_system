<?php

namespace Modules\Examinations\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Result\Models\Result;
use Modules\Subject\Models\Subject;

class Examination extends Model
{
    use HasFactory;

    /**
     * The sittings a term is split into. A term holds more than one round of
     * papers, and this is what an exam timetable is organised around.
     *
     * @var array<string, string>
     */
    public const EXAM_TYPES = [
        'mid_term' => 'Mid Term',
        'end_term' => 'End Term',
    ];

    protected $fillable = [
        'institution_id',
        'class_id',
        'title',
        'subject_id',
        'subject_name',
        'class_name',
        'term',
        'exam_type',
        'academic_year',
        'exam_date',
        'start_time',
        'end_time',
        'total_marks',
        'passing_marks',
        'notes',
    ];

    protected $casts = [
        'academic_year' => 'integer',
        'exam_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function results()
    {
        return $this->hasMany(Result::class);
    }

    /**
     * How the sitting reads on screen. Untyped examinations predate the
     * column and simply haven't been assigned to one yet.
     */
    public function examTypeLabel(): string
    {
        return self::EXAM_TYPES[$this->exam_type] ?? 'Not specified';
    }

    /**
     * How long the paper runs, or null when no times were recorded.
     */
    public function durationLabel(): ?string
    {
        if (! $this->start_time || ! $this->end_time) {
            return null;
        }

        $minutes = $this->start_time->diffInMinutes($this->end_time);

        if ($minutes <= 0) {
            return null;
        }

        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        return trim(($hours ? $hours.'h ' : '').($remainder ? $remainder.'m' : ''));
    }
}
