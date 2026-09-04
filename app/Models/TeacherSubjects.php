<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Subject\Models\Subject;

/**
 * "Mr Otieno teaches Mathematics."
 *
 * The subjects a teacher owns marks for. Paired with {@see TeacherClasses}
 * (the classes a teacher is responsible for), it answers the question the
 * marks sheet asks: which class, then which of your subjects.
 *
 * @property int $id
 * @property int $teacher_id
 * @property int $subject_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TeacherSubjects extends Model
{
    protected $fillable = [
        'teacher_id',
        'subject_id',
    ];

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
