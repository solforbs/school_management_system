<?php

namespace Modules\Teacher\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Institution\Models\Institution;

class TeacherDetails extends Model
{
    use HasFactory, SoftDeletes;

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

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }
}
