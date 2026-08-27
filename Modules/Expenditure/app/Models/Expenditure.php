<?php

namespace Modules\Expenditure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Institution\Models\Institution;

/**
 * One thing the school spent money on. Kept by the Accountant, read by the
 * Director - the outgoing counterpart to fee collection.
 *
 * @property int $id
 * @property int $institution_id
 * @property int|null $expenditure_category_id
 * @property int|null $recorded_by
 * @property string $title
 * @property string|null $payee
 * @property string $amount
 * @property Carbon $spent_on
 * @property string $payment_method
 * @property string|null $reference
 * @property string $status
 * @property Carbon|null $paid_at
 * @property string|null $receipt_path
 * @property string|null $notes
 * @property-read ExpenditureCategory|null $category
 * @property-read Institution|null $institution
 * @property-read User|null $recordedBy
 */
class Expenditure extends Model
{
    use HasFactory, SoftDeletes;

    /** @var string[] */
    public const PAYMENT_METHODS = ['cash', 'bank_transfer', 'mobile_money', 'cheque'];

    /** @var string[] */
    public const STATUSES = ['pending', 'approved', 'paid', 'cancelled'];

    protected $fillable = [
        'institution_id',
        'expenditure_category_id',
        'recorded_by',
        'title',
        'payee',
        'amount',
        'spent_on',
        'payment_method',
        'reference',
        'status',
        'paid_at',
        'receipt_path',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'spent_on' => 'date',
        'paid_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenditureCategory::class, 'expenditure_category_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Money actually out of the school's hands, as opposed to spending
     * that's only been recorded or approved so far.
     */
    public function scopeSettled($query)
    {
        return $query->where('status', 'paid');
    }
}
