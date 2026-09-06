<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Institution\Models\Institution;

/**
 * A school's reusable parent notice - "term opens", "mid-term break",
 * "closure". Scoped to one institution. The body/subject may contain
 * {{placeholders}}; see ParentNotification for how they're filled.
 *
 * @property int $id
 * @property int $institution_id
 * @property string $name
 * @property string $subject
 * @property string $body
 * @property int|null $created_by
 */
class NotificationTemplate extends Model
{
    protected $fillable = [
        'institution_id',
        'name',
        'subject',
        'body',
        'created_by',
    ];

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The distinct {{placeholder}} names used anywhere in the subject or
     * body, in first-seen order.
     *
     * @return list<string>
     */
    public function placeholders(): array
    {
        return ParentNotification::extractPlaceholders($this->subject.' '.$this->body);
    }
}
