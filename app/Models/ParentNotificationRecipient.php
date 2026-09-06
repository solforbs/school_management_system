<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One delivery attempt - a single parent on a single channel - for a
 * ParentNotification. Kept so the history view can say exactly who the
 * message reached and who it didn't.
 *
 * @property int $id
 * @property int $parent_notification_id
 * @property int|null $user_id
 * @property string $channel
 * @property string|null $destination
 * @property string $status
 * @property string|null $error
 * @property Carbon|null $sent_at
 */
class ParentNotificationRecipient extends Model
{
    protected $fillable = [
        'parent_notification_id',
        'user_id',
        'channel',
        'destination',
        'status',
        'error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ParentNotification, $this> */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(ParentNotification::class, 'parent_notification_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
