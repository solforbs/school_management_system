<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Subject\Models\Subject;

class TeacherSubjects extends Model
{
    protected $fillable = [
        'teacher_id',
        'subject_id',
    ];

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }
}
