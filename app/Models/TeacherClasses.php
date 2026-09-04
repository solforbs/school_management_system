<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Classes\Models\SchoolClass;

/**
 * "Mr Otieno teaches Form 2 East."
 *
 * The classes a teacher is responsible for. Paired with {@see TeacherSubjects}
 * (the subjects a teacher owns), it answers the question the marks sheet
 * asks: which class, then which of your subjects.
 *
 * @property int $id
 * @property int $teacher_id
 * @property int $class_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TeacherClasses extends Model
{
    protected $fillable = [
        'teacher_id',
        'class_id',
    ];

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
