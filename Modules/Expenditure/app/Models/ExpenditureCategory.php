<?php

namespace Modules\Expenditure\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Institution\Models\Institution;

/**
 * A heading a school files its spending under - "Salaries", "Utilities",
 * "Maintenance". Owned per institution so each school keeps its own chart
 * of accounts rather than a shared global list.
 *
 * @property int $id
 * @property int $institution_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property-read Institution|null $institution
 */
class ExpenditureCategory extends Model
{
    use HasFactory;

    /**
     * The headings almost every school ends up needing, offered as a
     * one-click starting point rather than making an Accountant type them
     * out before they can record anything.
     *
     * @var array<string, string>
     */
    public const DEFAULTS = [
        'Salaries & Wages' => 'Teaching and support staff payroll.',
        'Utilities' => 'Electricity, water, internet and airtime.',
        'Teaching & Learning Materials' => 'Textbooks, stationery, lab and workshop consumables.',
        'Food & Catering' => 'Kitchen supplies and boarding meals.',
        'Transport & Fuel' => 'School vehicles, fuel and hired transport.',
        'Repairs & Maintenance' => 'Buildings, furniture and equipment upkeep.',
        'Examination Costs' => 'Exam papers, printing and invigilation.',
        'Co-curricular Activities' => 'Games, clubs, music and drama festivals.',
        'Statutory & Licences' => 'Levies, permits and regulatory fees.',
        'Miscellaneous' => 'Anything without a heading of its own yet.',
    ];

    protected $fillable = [
        'institution_id',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function expenditures(): HasMany
    {
        return $this->hasMany(Expenditure::class);
    }
}
