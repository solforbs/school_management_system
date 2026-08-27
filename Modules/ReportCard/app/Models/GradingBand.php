<?php

namespace Modules\ReportCard\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Curriculum\Models\Curriculum;
use Modules\Institution\Models\Institution;

/**
 * One rung of a grading scale: "75-79% is a B+, worth 10 points".
 *
 * A band belongs to a curriculum when the school runs more than one
 * (8-4-4 alongside CBC). A null curriculum_id is the school-wide
 * fallback - what every band configured before curricula were split out
 * still is.
 */
class GradingBand extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'curriculum_id',
        'min_percentage',
        'max_percentage',
        'grade',
        'points',
        'remark',
    ];

    protected $casts = [
        'min_percentage' => 'decimal:2',
        'max_percentage' => 'decimal:2',
        'points' => 'integer',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function curriculum()
    {
        return $this->belongsTo(Curriculum::class);
    }
}
