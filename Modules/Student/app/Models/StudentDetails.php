<?php

namespace Modules\Student\Models;

use App\Concerns\TetherSyncable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;

// use Modules\Student\Database\Factories\StudentDetailsFactory;

class StudentDetails extends Model
{
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

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }
}
