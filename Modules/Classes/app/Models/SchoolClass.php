<?php

namespace Modules\Classes\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Curriculum\Models\Curriculum;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\Lesson\Models\Lesson;
use Modules\Result\Models\Result;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\SubjectTeacher;
use Modules\Timetable\Models\TimetableEntry;

/**
 * @property int $id
 * @property int $institution_id
 * @property string $name
 * @property string|null $level
 * @property int|null $curriculum_id
 * @property int|null $class_teacher_id
 * @property int|null $capacity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Institution|null $institution
 * @property-read Curriculum|null $curriculum
 * @property-read User|null $classTeacher
 */
class SchoolClass extends Model
{
    use HasFactory;

    /**
     * Table is "classes" - the model can't be named Class since that's a
     * reserved word in PHP.
     */
    protected $table = 'classes';

    protected $fillable = [
        'institution_id',
        'name',
        'level',
        'curriculum_id',
        'class_teacher_id',
        'capacity',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Which curriculum this class sits on, and so which grading scale its
     * results are marked against. Null while a school hasn't split its
     * classes across curricula, in which case the school-wide scale
     * applies.
     */
    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'class_teacher_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(StudentDetails::class, 'class_id');
    }

    public function subjectTeachers(): HasMany
    {
        return $this->hasMany(SubjectTeacher::class, 'class_id');
    }

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class, 'class_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'class_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class, 'class_id');
    }

    public function examinations(): HasMany
    {
        return $this->hasMany(Examination::class, 'class_id');
    }
}
