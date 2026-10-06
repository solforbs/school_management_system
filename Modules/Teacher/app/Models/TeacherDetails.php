<?php

namespace Modules\Teacher\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Institution\Models\Institution;

/**
 * @property int $id
 * @property int $teacher_id
 * @property int $institution_id
 * @property string|null $phone
 * @property string|null $employee_number
 * @property string|null $department
 * @property string|null $qualification
 * @property Carbon|null $hire_date
 * @property string|null $address
 * @property bool $is_active
 * @property string|null $status
 * @property string|null $notes
 * @property bool $can_enroll_students
 * @property-read User|null $teacher
 * @property-read Institution|null $institution
 */
class TeacherDetails extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'teacher_id',
        'institution_id',
        'phone',
        'employee_number',
        'department',
        'qualification',
        'hire_date',
        'address',
        'is_active',
        'status',
        'notes',
        'can_enroll_students',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'is_active' => 'boolean',
        'can_enroll_students' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
