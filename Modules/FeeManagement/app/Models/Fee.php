<?php

namespace Modules\FeeManagement\Models;

use App\Concerns\TetherSyncable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Institution\Models\Institution;

/**
 * @property int $id
 * @property int $institution_id
 * @property int $student_id
 * @property int|null $parent_id
 * @property string $title
 * @property string $fee_type
 * @property float|string $amount
 * @property float|string|null $amount_paid
 * @property Carbon|null $due_date
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Fee extends Model
{
    use HasFactory, SoftDeletes, TetherSyncable;

    /**
     * amount_paid is deliberately absent: it's an aggregate derived from
     * FeePayment rows, so an offline device must never write it directly.
     * ReconcileSyncedFeeBalances recomputes it after each push.
     *
     * @var string[]
     */
    protected array $tetherSyncable = [
        // Present so the value TetherServiceProvider forces onto every
        // inbound mutation survives filtering - never the client's own.
        'institution_id',
        'student_id',
        'parent_id',
        'title',
        'fee_type',
        'amount',
        'due_date',
        'notes',
    ];

    protected $fillable = [
        'institution_id',
        'student_id',
        'parent_id',
        'title',
        'fee_type',
        'amount',
        'amount_paid',
        'due_date',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function payments()
    {
        return $this->hasMany(FeePayment::class);
    }

    public function getBalanceAttribute(): float
    {
        return round((float) $this->amount - (float) $this->amount_paid, 2);
    }

    public function getPaidAmountAttribute(): float
    {
        return (float) ($this->amount_paid ?? 0);
    }

    public function getStatusAttribute(): string
    {
        if ($this->balance <= 0) {
            return 'paid';
        }

        if ((float) $this->amount_paid > 0) {
            return 'partial';
        }

        if ($this->due_date && $this->due_date->isPast()) {
            return 'overdue';
        }

        return 'pending';
    }

    public function scopePaid($query)
    {
        return $query->whereColumn('amount_paid', '>=', 'amount');
    }

    public function scopePartial($query)
    {
        return $query->where('amount_paid', '>', 0)->whereColumn('amount_paid', '<', 'amount');
    }

    public function scopeOverdue($query)
    {
        return $query->whereColumn('amount_paid', '<', 'amount')->where('due_date', '<', now()->toDateString());
    }

    public function scopePending($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('amount_paid')->orWhere('amount_paid', '<=', 0);
        })->where(function ($q) {
            $q->whereNull('due_date')->orWhere('due_date', '>=', now()->toDateString());
        });
    }

    public function scopeWithStatus($query, string $status)
    {
        return match ($status) {
            'paid' => $query->paid(),
            'partial' => $query->partial(),
            'overdue' => $query->overdue(),
            'pending' => $query->pending(),
            default => $query,
        };
    }
}
