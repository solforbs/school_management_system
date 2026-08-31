<?php

namespace Modules\Classes\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Curriculum\Models\Curriculum;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\Lesson\Models\Lesson;
use Modules\Result\Models\Result;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\SubjectTeacher;
use Modules\Timetable\Models\TimetableEntry;

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

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Which curriculum this class sits on, and so which grading scale its
     * results are marked against. Null while a school hasn't split its
     * classes across curricula, in which case the school-wide scale
     * applies.
     */
    public function curriculum()
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function classTeacher()
    {
        return $this->belongsTo(User::class, 'class_teacher_id');
    }

    public function students()
    {
        return $this->hasMany(StudentDetails::class, 'class_id');
    }

    public function subjectTeachers()
    {
        return $this->hasMany(SubjectTeacher::class, 'class_id');
    }

    public function timetableEntries()
    {
        return $this->hasMany(TimetableEntry::class, 'class_id');
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class, 'class_id');
    }

    public function results()
    {
        return $this->hasMany(Result::class, 'class_id');
    }

    public function examinations()
    {
        return $this->hasMany(Examination::class, 'class_id');
    }
}
