<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    /**
     * The full set of toggleable feature keys, mapped to their admin-facing
     * label and description. Single source of truth for both the site
     * settings page and SettingSeeder's defaults.
     */
    public const FEATURES = [
        'sms' => [
            'label' => 'SMS notifications',
            'description' => 'Send SMS messages (e.g. fee payment reminders) via the configured SMS provider.',
        ],
        'email_notifications' => [
            'label' => 'Email notifications',
            'description' => 'Send notification emails (e.g. fee payment reminders) to parents.',
        ],
    ];

    /**
     * The one-off fee a school pays to be set up, charged during director
     * onboarding before their institution is created. Zero or unset means
     * onboarding asks for no payment at all.
     */
    public const SETUP_FEE = 'onboarding_setup_fee';

    protected $fillable = [
        'key',
        'enabled',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    /**
     * A setting that carries an amount or a string rather than a yes/no.
     * Cached the same way isEnabled() is, and busted by put().
     */
    public static function value(string $key, ?string $default = null): ?string
    {
        $stored = Cache::rememberForever(
            "settings.value.{$key}",
            // Coalesced to '' because rememberForever treats a cached null
            // as a miss and would re-query on every single request.
            fn () => static::query()->where('key', $key)->value('value') ?? '',
        );

        return $stored === '' ? $default : $stored;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget("settings.value.{$key}");
    }

    /**
     * The setup fee as an amount. Never negative, whatever is in the row -
     * a negative fee would mean handing money back at signup.
     */
    public static function setupFee(): float
    {
        return max(0.0, round((float) static::value(self::SETUP_FEE, '0'), 2));
    }

    /**
     * Whether the given feature is enabled. Cached indefinitely and busted
     * on write by set(), so callers can check this on every request without
     * hitting the database each time.
     */
    public static function isEnabled(string $key): bool
    {
        return Cache::rememberForever(
            "settings.{$key}",
            fn () => static::query()->where('key', $key)->value('enabled') ?? true,
        );
    }

    /**
     * Enable/disable a feature and bust its cached value immediately, so the
     * change is reflected on the very next request.
     */
    public static function set(string $key, bool $enabled): void
    {
        static::query()->updateOrCreate(['key' => $key], ['enabled' => $enabled]);

        Cache::forget("settings.{$key}");
    }
}
