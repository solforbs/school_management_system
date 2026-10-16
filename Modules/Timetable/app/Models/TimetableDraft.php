<?php

namespace Modules\Timetable\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;

class TimetableDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'name',
        'academic_year',
        'term',
        'structure_id',
        'status',
        'scope_type',
        'scope_id',
        'generator_settings',
        'generation_score',
        'conflict_summary',
        'created_by',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'generator_settings' => 'array',
        'conflict_summary' => 'array',
        'generation_score' => 'float',
        'published_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(TimetableStructure::class, 'structure_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function draftEntries(): HasMany
    {
        return $this->hasMany(TimetableDraftEntry::class, 'draft_id');
    }

    public function targetClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'scope_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
