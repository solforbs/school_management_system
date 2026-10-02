<?php

namespace Modules\Result\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\Subject\Models\Subject;

class Result extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'class_id',
        'subject_id',
        'student_id',
        'examination_id',
        'marks_obtained',
        'grade',
        'cbc_band',
        'remarks',
        'recorded_by',
    ];

    protected $casts = [
        'marks_obtained' => 'decimal:2',
    ];

    /**
     * Whether this result was entered as a direct CBC band by a teacher
     * rather than derived from a numerical mark.
     *
     * Null marks_obtained + a filled cbc_band = the teacher picked
     * EE/ME/AE/BE directly on a formative task.
     */
    public function isDirectBand(): bool
    {
        return $this->cbc_band !== null && $this->marks_obtained === null;
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * The subject this mark is for. Recorded on the result itself now that
     * an examination can be a whole-school sitting spanning every subject.
     * Older rows were backfilled from their examination.
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function examination()
    {
        return $this->belongsTo(Examination::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
