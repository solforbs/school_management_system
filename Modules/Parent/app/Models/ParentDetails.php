<?php

namespace Modules\Parent\Models;

use App\Concerns\TetherSyncable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

// use Modules\Parent\Database\Factories\ParentDetailsFactory;

/**
 * @property int $id
 * @property int $parent_id
 * @property int|null $institution_id
 * @property string|null $parent_phone
 * @property string|null $parent_occupation
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ParentDetails extends Model
{
    use HasFactory, TetherSyncable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'parent_id',
        'institution_id',
        'parent_phone',
        'parent_occupation',
    ];

    // protected static function newFactory(): ParentDetailsFactory
    // {
    //     // return ParentDetailsFactory::new();
    // }
    /** @return BelongsTo<User, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
