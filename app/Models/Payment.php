<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Institution\Models\Institution;

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

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function initiatedBy()
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
