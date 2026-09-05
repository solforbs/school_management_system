<?php

namespace Athwari\LaravelZktecoAdms\Models;

use Athwari\LaravelZktecoAdms\Database\Factories\ZktecoAttendanceLogFactory;
use Athwari\LaravelZktecoAdms\Enums\AttendanceStatus;
use Athwari\LaravelZktecoAdms\Enums\VerifyMode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $device_id
 * @property string $pin
 * @property Carbon $recorded_at
 * @property Carbon|null $occurred_at
 * @property AttendanceStatus|null $status
 * @property VerifyMode $verify_mode
 * @property string $work_code
 * @property string|null $reserved_1
 * @property string|null $reserved_2
 * @property array<string, mixed>|null $raw_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ZktecoAttendanceLog extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return ZktecoAttendanceLogFactory::new();
    }

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => AttendanceStatus::class,
        'verify_mode' => VerifyMode::class,
        'recorded_at' => 'datetime',
        'occurred_at' => 'datetime',
        'raw_data' => 'array',
    ];

    public function getTable(): string
    {
        return config('zkteco-adms.table_prefix', 'zkteco_').'attendance_logs';
    }

    /** @return BelongsTo<Model, $this> */
    public function device(): BelongsTo
    {
        $model = config('zkteco-adms.models.device', ZktecoDevice::class);

        return $this->belongsTo($model, 'device_id');
    }

    /** @return BelongsTo<Model, $this> */
    public function zktecoUser(): BelongsTo
    {
        $model = config('zkteco-adms.models.user', ZktecoUser::class);

        return $this->belongsTo($model, 'pin', 'pin');
    }
}
