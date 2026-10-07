<?php

namespace Modules\Student\Models;

use App\Concerns\TetherSyncable;
use App\Models\User;
use App\Services\StudentCacheService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;

// use Modules\Student\Database\Factories\StudentDetailsFactory;
/**
 * @property int $id
 * @property int $student_id
 * @property int $institution_id
 * @property string|null $profile_photo
 * @property string|null $student_number
 * @property string|null $admission_number
 * @property bool $is_active
 * @property string|null $guardian_name
 * @property string|null $guardian_phone
 * @property string|null $guardian_email
 * @property int|null $class_id
 * @property string|null $gender
 * @property int|null $parent_id
 * @property-read User|null $student
 * @property-read Institution|null $institution
 * @property-read SchoolClass|null $schoolClass
 */
class StudentDetails extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory, SoftDeletes, TetherSyncable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'phone',
        'user_id',
        'student_id',
        'date_of_birth',
        'gender',
        'admission_number',
        'student_number',
        'address',
        'city',
        'state',
        'country',
        'parent_name',
        'parent_id',
        'parent_phone',
        'parent_email',
        'parent_occupation',
        'guardian_name',
        'guardian_phone',
        'guardian_email',
        'guardian_relationship',
        'medical_conditions',
        'allergies',
        'special_needs',
        'is_active',
        'enrollment_status',
        'institution_id',
        'class_id',
        'profile_photo',
        'notes',
    ];

    // protected static function newFactory(): StudentDetailsFactory
    // {
    //     // return StudentDetailsFactory::new();
    // }

    protected static function booted(): void
    {
        static::saved(function (StudentDetails $student) {
            $service = app(StudentCacheService::class);
            $service->invalidateClass((int) $student->institution_id, $student->class_id ? (int) $student->class_id : null);
            if ($student->isDirty('class_id') && $student->getOriginal('class_id')) {
                $service->invalidateClass((int) $student->institution_id, (int) $student->getOriginal('class_id'));
            }
        });

        static::deleted(function (StudentDetails $student) {
            $service = app(StudentCacheService::class);
            $service->invalidateClass((int) $student->institution_id, $student->class_id ? (int) $student->class_id : null);
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
