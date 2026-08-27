<?php

namespace Modules\Subject\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'name',
        'code',
        'is_compulsory',
        'is_active',
    ];

    protected $casts = [
        'is_compulsory' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function examinations()
    {
        return $this->hasMany(Examination::class);
    }

    /**
     * Who teaches this subject, and to which class. One row per
     * (class, teacher) pair - a subject taught in five classes has five.
     */
    public function teacherAssignments()
    {
        return $this->hasMany(SubjectTeacher::class);
    }
}
