<?php

namespace App\Models;

use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Database\Factories\DevicesFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Institution\Models\Institution;

/**
 * @property int $id
 * @property string $serial_number
 * @property int|null $institution_id
 * @property int|null $zkteco_device_id
 * @property bool $is_active
 * @property-read Institution|null $institution
 * @property-read ZktecoDevice|null $zktecoDevice
 */
class Devices extends Model
{
    /** @use HasFactory<DevicesFactory> */
    use HasFactory;

    protected $fillable = [
        'serial_number',
        'institution_id',
        'zkteco_device_id',
        'is_active',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function zktecoDevice(): BelongsTo
    {
        return $this->belongsTo(ZktecoDevice::class, 'serial_number', 'serial_number');
    }

    /**
     * Match (or create) the ZktecoDevice for this device's serial number and
     * link it via zkteco_device_id. This is the single place that governs
     * "create a device even offline, then match it up by serial number once
     * it's seen" - the physical device is never assumed to exist yet, and
     * an already-connected device's real details are never clobbered with
     * placeholder values.
     */
    public function linkZktecoDevice(?string $name = null, ?string $ipAddress = null): ZktecoDevice
    {
        $zktecoDevice = ZktecoDevice::firstOrCreate(
            ['serial_number' => $this->serial_number],
            [
                'name' => $name ?: $this->serial_number,
                'ip_address' => $ipAddress,
                'options' => [],
            ]
        );

        if ($name || $ipAddress) {
            $zktecoDevice->fill(array_filter([
                'name' => $name,
                'ip_address' => $ipAddress,
            ]))->save();
        }

        $this->zkteco_device_id = $zktecoDevice->id;
        $this->save();

        return $zktecoDevice;
    }
}
