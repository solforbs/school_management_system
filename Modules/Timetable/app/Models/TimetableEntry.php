<?php

namespace Modules\Timetable\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Lesson\Models\Lesson;
use Modules\Subject\Models\Subject;

class TimetableEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'class_id',
        'teacher_id',
        'class_name',
        'subject',
        'subject_id',
        'day_of_week',
        'start_time',
        'end_time',
        'room',
        'notes',
    ];

    protected $casts = [
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

    /**
     * Named to avoid colliding with the legacy free-text `subject` column
     * (Eloquent's attribute lookup would always win over a same-named
     * relation method) - same reason schoolClass() isn't called class().
     */
    public function subjectRecord()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }

    public function isDoubleLesson(): bool
    {
        return str_contains((string) $this->notes, '[Double Lesson');
    }

    public function isDoubleLessonPart1(): bool
    {
        return str_contains((string) $this->notes, '[Double Lesson Part 1/2]');
    }

    public function isDoubleLessonPart2(): bool
    {
        return str_contains((string) $this->notes, '[Double Lesson Part 2/2]');
    }
}
