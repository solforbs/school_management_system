<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Institution\Models\Institution;

/**
 * @property int $id
 * @property int|null $institution_id
 * @property int|null $plan_id
 * @property string $purpose
 * @property int|null $initiated_by
 * @property string $reference
 * @property string|null $gateway_reference
 * @property string $amount
 * @property string $currency
 * @property string|null $channel
 * @property string $status
 * @property Carbon|null $paid_at
 * @property array<string, mixed>|null $gateway_response
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Institution|null $institution a setup fee taken before the school exists has none yet
 * @property-read Plan|null $plan
 * @property-read User|null $initiatedBy
 */
class Payment extends Model
{
    /**
     * The one-off charge taken during onboarding, before the school exists.
     */
    public const PURPOSE_SETUP = 'setup';

    /**
     * A payment towards a subscription period - the ordinary case.
     */
    public const PURPOSE_SUBSCRIPTION = 'subscription';

    protected $fillable = [
        'institution_id',
        'plan_id',
        'purpose',
        'initiated_by',
        'reference',
        'gateway_reference',
        'amount',
        'currency',
        'channel',
        'status',
        'paid_at',
        'gateway_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'gateway_response' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'successful';
    }

    public function isSetupFee(): bool
    {
        return $this->purpose === self::PURPOSE_SETUP;
    }

    /**
     * A settled setup fee that no institution has claimed yet - what lets
     * its payer through to the "create your school" step.
     */
    public function scopeUnclaimedSetupFee(Builder $query): Builder
    {
        return $query->where('purpose', self::PURPOSE_SETUP)
            ->where('status', 'successful')
            ->whereNull('institution_id');
    }
}
