<?php

namespace Modules\Attendance\Models;

use App\Models\Devices;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendances';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'institution_id',
        'school_class_id',
        'student_id',
        'date',
        'status',
        'source',
        'device_id',
        'scanned_at',
        'recorded_by',
        'parent_notified',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'scanned_at' => 'datetime',
            'parent_notified' => 'boolean',
        ];
    }

    /**
     * The student this attendance record belongs to.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Additional student profile details (admission number, etc.).
     */
    public function studentDetails(): HasOne
    {
        return $this->hasOne(StudentDetails::class, 'student_id', 'student_id');
    }

    /**
     * The school class for which roll-call was taken.
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    /**
     * The institution to which this record belongs.
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id');
    }

    /**
     * The teacher or staff member who verified and recorded this roll-call.
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * The hardware device that captured the biometric gate check-in, if applicable.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Devices::class, 'device_id');
    }

    public function isPresent(): bool
    {
        return $this->status === 'present';
    }

    public function isAbsent(): bool
    {
        return $this->status === 'absent';
    }

    public function isBiometric(): bool
    {
        return in_array($this->source, ['biometric', 'verified'], true);
    }

    public function isOverride(): bool
    {
        return $this->source === 'override';
    }
}
