<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

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

    /**
     * Google OAuth for the "Continue with Google" login button. An Admin
     * fills these on the site settings page; they take precedence over the
     * matching config/services.php (.env) values, so a deploy needn't ship
     * the secret in its environment.
     */
    public const GOOGLE_LOGIN = 'google_login';

    public const GOOGLE_CLIENT_ID = 'google_oauth_client_id';

    public const GOOGLE_CLIENT_SECRET = 'google_oauth_client_secret';

    public const GOOGLE_REDIRECT = 'google_oauth_redirect';

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
     * Read a value that was stored encrypted via putSecret(). Falls back to
     * returning the raw value if it isn't decryptable, so a hand-set plain
     * value still works.
     */
    public static function secret(string $key): ?string
    {
        $raw = static::value($key);

        if ($raw === null) {
            return null;
        }

        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return $raw;
        }
    }

    /**
     * Store a secret encrypted at rest. A blank value leaves the existing
     * one untouched, so a settings form needn't re-enter it every save.
     */
    public static function putSecret(string $key, ?string $value): void
    {
        if (blank($value)) {
            return;
        }

        static::put($key, Crypt::encryptString($value));
    }

    /**
     * Google OAuth config - database first, config/services.php (.env) as
     * the fallback.
     *
     * @return array{client_id: ?string, client_secret: ?string, redirect: ?string}
     */
    public static function googleOauth(): array
    {
        return [
            'client_id' => static::value(self::GOOGLE_CLIENT_ID) ?: config('services.google.client_id'),
            'client_secret' => static::secret(self::GOOGLE_CLIENT_SECRET) ?: config('services.google.client_secret'),
            'redirect' => static::value(self::GOOGLE_REDIRECT) ?: config('services.google.redirect'),
        ];
    }

    /**
     * Whether the "Continue with Google" button should appear: switched on,
     * and a client id resolvable from somewhere.
     */
    public static function googleLoginEnabled(): bool
    {
        return static::isEnabled(self::GOOGLE_LOGIN) && filled(static::googleOauth()['client_id']);
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
