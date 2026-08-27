<?php

namespace Modules\Subject\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;

/**
 * "Mr Otieno teaches Mathematics to Form 2 East."
 *
 * The explicit answer to which subjects a teacher may enter results for.
 * Before this, that was inferred from the timetable, which answers a
 * different question - who is standing in that room at 10am - and left a
 * school with no way to say a teacher owns a subject's marks without also
 * scheduling them a period for it.
 *
 * @property int $id
 * @property int $institution_id
 * @property int $class_id
 * @property int $subject_id
 * @property int $teacher_id
 * @property int|null $assigned_by
 * @property-read SchoolClass|null $schoolClass
 * @property-read Subject|null $subject
 * @property-read User|null $teacher
 */
class SubjectTeacher extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'class_id',
        'subject_id',
        'teacher_id',
        'assigned_by',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Named schoolClass() rather than class() - "class" is a reserved word
     * in PHP, the same reason the model itself is SchoolClass.
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
