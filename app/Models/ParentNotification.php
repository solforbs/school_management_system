<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Institution\Models\Institution;

/**
 * One notice a Director sends to the school's parents.
 *
 * Lifecycle: `pending` (queued, or waiting for its scheduled time) ->
 * `sending` -> `sent` (at least one delivery succeeded) or `failed` (none
 * did). `canceled` is a scheduled send a Director called off before it ran.
 *
 * @property int $id
 * @property int $institution_id
 * @property int|null $template_id
 * @property string $name
 * @property string $subject
 * @property string $body
 * @property array<int, string> $channels
 * @property array<string, string>|null $placeholder_values
 * @property Carbon|null $scheduled_for
 * @property string $status
 * @property int $recipients_count
 * @property int $delivered_count
 * @property int $failed_count
 * @property Carbon|null $sent_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ParentNotification extends Model
{
    public const CHANNELS = ['sms', 'email'];

    /**
     * Placeholders resolved per recipient at send time - everything else
     * is a value the Director types on the send screen.
     */
    public const AUTO_PLACEHOLDERS = ['institution', 'parent', 'date'];

    protected $fillable = [
        'institution_id',
        'template_id',
        'name',
        'subject',
        'body',
        'channels',
        'placeholder_values',
        'scheduled_for',
        'status',
        'recipients_count',
        'delivered_count',
        'failed_count',
        'sent_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'placeholder_values' => 'array',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<NotificationTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ParentNotificationRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(ParentNotificationRecipient::class);
    }

    /**
     * A send waiting for its time or currently queued/sending - states
     * a Director can stop or call off.
     */
    public function isCancellable(): bool
    {
        return in_array($this->status, ['pending', 'sending'], true);
    }

    public function usesChannel(string $channel): bool
    {
        return in_array($channel, $this->channels, true);
    }

    /**
     * The distinct {{placeholder}} names in a piece of text, first-seen
     * order. Accepts `{{ name }}` or `{{name}}`.
     *
     * @return list<string>
     */
    public static function extractPlaceholders(string $text): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $text, $matches);

        $names = [];

        foreach ($matches[1] as $raw) {
            $name = Str::lower($raw);

            if (! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * The placeholders a Director must supply a value for: everything in
     * the text that isn't resolved automatically per recipient.
     *
     * @return list<string>
     */
    public function customPlaceholders(): array
    {
        return array_values(array_diff(
            self::extractPlaceholders($this->subject.' '.$this->body),
            self::AUTO_PLACEHOLDERS,
        ));
    }

    /**
     * Fill a string's placeholders. `$context` supplies the per-recipient
     * auto values (institution, parent); `$values` the Director-typed ones.
     *
     * @param  array<string, string>  $context
     * @param  array<string, string>  $values
     */
    public static function render(string $text, array $context = [], array $values = []): string
    {
        $all = array_merge(
            ['date' => now()->format('j M Y')],
            $values,
            $context,
        );

        return preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            fn (array $m): string => $all[Str::lower($m[1])] ?? $m[0],
            $text,
        ) ?? $text;
    }
}
