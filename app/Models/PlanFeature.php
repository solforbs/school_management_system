<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One pro-tier capability a plan can switch on.
 *
 * The plans table stores a bare list of feature keys; this is where a key
 * gets the wording a pricing page prints. New rows are added from the plan
 * admin screen (see App\Livewire\Admin\PlanFeatureManager), which is why
 * this is a table rather than a PHP constant.
 */
class PlanFeature extends Model
{
    /**
     * Request-lifetime memo of key => label, so callers in a loop
     * (Plan::inclusions(), PlanResource) don't each hit the table.
     *
     * @var array<string, string>|null
     */
    protected static ?array $catalog = null;

    protected $fillable = [
        'key',
        'label',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Turn a human label into a stable key: lowercase, words joined by
     * underscores. "Bulk SMS" -> "bulk_sms".
     */
    public static function keyFor(string $label): string
    {
        return Str::of($label)->slug('_')->toString();
    }

    /**
     * key => label for every active feature, with the constants the code
     * ships with as a fallback for keys not (yet) in the table.
     *
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        if (static::$catalog === null) {
            $catalog = Plan::FEATURES;

            static::query()
                ->where('is_active', true)
                ->orderBy('label')
                ->get(['key', 'label'])
                ->each(function (self $feature) use (&$catalog) {
                    $catalog[$feature->key] = $feature->label;
                });

            static::$catalog = $catalog;
        }

        return static::$catalog;
    }

    /**
     * Drop the memo - call after writing to the table within one request.
     */
    public static function flushCatalog(): void
    {
        static::$catalog = null;
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCatalog());
        static::deleted(fn () => static::flushCatalog());
    }
}
