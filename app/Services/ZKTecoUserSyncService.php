<?php

namespace App\Services;

use Athwari\LaravelZktecoAdms\DTOs\UserRecord;
use Athwari\LaravelZktecoAdms\Models\ZktecoUser;
use Athwari\LaravelZktecoAdms\Services\CommandManager;
use Athwari\LaravelZktecoAdms\Services\DeviceManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
/**
 * @param array<string, string> $userData
 */
class ZKTecoUserSyncService
{
    private DeviceManager $deviceManager;

    private CommandManager $commandManager;

    public function __construct(
        DeviceManager $deviceManager,
        CommandManager $commandManager
    ) {
        $this->deviceManager = $deviceManager;
        $this->commandManager = $commandManager;
    }

    /**
     * Add a user to a ZKTeco device.
     *
     * @param  string  $deviceSerial  The serial number of the device
     * @param  array  $userData  User data with keys: pin, name, privilege, card, password, photo_path (optional, absolute filesystem path)
     * @return bool True if user was added/updated successfully
     */
    public function addUserToDevice(string $deviceSerial, array $userData): bool
    {
        try {
            // Validate required fields
            if (empty($userData['pin']) || empty($userData['name'])) {
                Log::error('PIN and Name are required to add user to device', [
                    'device' => $deviceSerial,
                    'user_data' => $userData,
                ]);

                return false;
            }

            // Check if device exists
            $device = $this->deviceManager->getDevice($deviceSerial);
            if (! $device) {
                Log::error('Device not found', ['device' => $deviceSerial]);

                return false;
            }

            // Build only the parameters using tabs
            $paramParts = [
                sprintf('PIN=%s', (string) $userData['pin']),
                sprintf('Name=%s', $userData['name']),
                sprintf('Pri=%d', (int) ($userData['privilege'] ?? 0)),
            ];

            // Add Card if provided
            if (! empty($userData['card'])) {
                $paramParts[] = sprintf('Card=%s', (string) $userData['card']);
            }

            // Add Password if provided. The ZKTeco push protocol field for the
            // verification password is "Passwd" (not "Password") -- devices
            // silently ignore unrecognized fields and still ACK the command,
            // which is why a wrong field name here looks like a successful sync
            // even though the password was never actually written to the device.
            if (isset($userData['password']) && $userData['password'] !== '') {
                // K40 Pro (and most ZKTeco keypad devices) only accept a numeric
                // password of up to 6 digits, so non-digit characters are stripped.
                $cleanPassword = substr(preg_replace('/\D/', '', $userData['password']), 0, 6);

                if (! empty($cleanPassword)) {
                    $paramParts[] = sprintf('Passwd=%s', $cleanPassword);
                } else {
                    Log::warning('Password could not be synced to device: no digits found after cleaning', [
                        'device' => $deviceSerial,
                        'pin' => $userData['pin'],
                    ]);
                }
            }
            // FIX: Put a SPACE after the action verb, and separate parameters with tabs (\t)
            // Correct format: DATA UPDATE USERINFO PIN=101\tName=John\tPri=0
            $commandString = 'DATA UPDATE USERINFO '.implode("\t", $paramParts);

            Log::debug('Queuing DATA UPDATE USERINFO command with password', [
                'device' => $deviceSerial,
                'command' => $commandString,
                'pin' => $userData['pin'],
                'password' => isset($userData['password']) && $userData['password'] !== '' ? 'set' : 'not set',
            ]);

            // Queue the command
            $this->commandManager->queueCommand($deviceSerial, $commandString);

            // Also store in local ZktecoUser model for reference
            ZktecoUser::updateOrCreate(
                ['pin' => (string) $userData['pin']],
                [
                    'name' => $userData['name'],
                    'card_number' => $userData['card'] ?? null,
                    'privilege' => (int) ($userData['privilege'] ?? 0),
                    'app_user_id' => $userData['app_user_id'] ?? null,
                ]
            );

            Log::info('User command queued successfully', [
                'device' => $deviceSerial,
                'pin' => $userData['pin'],
                'name' => $userData['name'],
                'password_set' => ! empty($userData['password']),
                'card_set' => ! empty($userData['card']),
            ]);

            // Clear cache for this device
            Cache::forget("device_users_{$deviceSerial}");

            if (! empty($userData['photo_path'])) {
                $this->queuePhotoCommand($deviceSerial, (string) $userData['pin'], (string) $userData['photo_path']);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to add user to device', [
                'device' => $deviceSerial,
                'pin' => $userData['pin'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Check if a user exists on a device using UserRecord DTO.
     *
     * @param  string  $deviceSerial  The serial number of the device
     * @param  string  $pin  The PIN to check
     * @return bool True if the user exists on the device
     */
    public function userExistsOnDevice(string $deviceSerial, string $pin): bool
    {
        try {
            // Get the device
            $device = $this->deviceManager->getDevice($deviceSerial);
            if (! $device) {
                Log::warning('Device not found when checking user existence', [
                    'device' => $deviceSerial,
                ]);

                return false;
            }

            // Check cache first
            $cacheKey = "device_users_{$deviceSerial}";
            $users = Cache::remember($cacheKey, 300, function () use ($deviceSerial, $device) {
                // Query all users from the device
                $this->commandManager->sendQueryUsersCommand($deviceSerial);

                // Get users from the local ZktecoUser model
                // These are synced when the device responds with USERINFO data
                return ZktecoUser::where('device_id', $device->id)->get();
            });

            // Check if the PIN exists in the user list
            $exists = $users->contains('pin', (string) $pin);

            if ($exists) {
                Log::debug('User exists on device', [
                    'device' => $deviceSerial,
                    'pin' => $pin,
                ]);
            } else {
                Log::debug('User does not exist on device', [
                    'device' => $deviceSerial,
                    'pin' => $pin,
                ]);
            }

            return $exists;
        } catch (\Exception $e) {
            Log::warning('Failed to check user existence on device', [
                'device' => $deviceSerial,
                'pin' => $pin,
                'error' => $e->getMessage(),
            ]);

            return false; // Assume user doesn't exist
        }
    }

    /**
     * Get a user from the device by PIN.
     *
     * @param  string  $deviceSerial  The serial number of the device
     * @param  string  $pin  The PIN to search for
     * @return UserRecord|null The user record or null if not found
     */
    public function getUserFromDevice(string $deviceSerial, string $pin): ?UserRecord
    {
        try {
            $device = $this->deviceManager->getDevice($deviceSerial);
            if (! $device) {
                Log::warning('Device not found when getting user', [
                    'device' => $deviceSerial,
                ]);

                return null;
            }

            // Query users from device
            $this->commandManager->sendQueryUsersCommand($deviceSerial);

            // Get user from local model
            $zktecoUser = ZktecoUser::where('device_id', $device->id)
                ->where('pin', (string) $pin)
                ->first();

            if (! $zktecoUser) {
                Log::debug('User not found in local database', [
                    'device' => $deviceSerial,
                    'pin' => $pin,
                ]);

                return null;
            }

            // Create UserRecord DTO
            return new UserRecord(
                pin: (string) $zktecoUser->pin,
                name: $zktecoUser->name ?? '',
                privilege: (int) ($zktecoUser->privilege ?? 0),
                card: $zktecoUser->card_number ?? '',
                password: '', // Password is not stored in the model for security
            );
        } catch (\Exception $e) {
            Log::error('Failed to get user from device', [
                'device' => $deviceSerial,
                'pin' => $pin,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get all users from a device.
     *
     * @param  string  $deviceSerial  The serial number of the device
     * @return UserRecord[] Array of UserRecord DTOs
     */
    public function getAllUsersFromDevice(string $deviceSerial): array
    {
        try {
            $device = $this->deviceManager->getDevice($deviceSerial);
            if (! $device) {
                Log::warning('Device not found when getting all users', [
                    'device' => $deviceSerial,
                ]);

                return [];
            }

            // Query users from device
            $this->commandManager->sendQueryUsersCommand($deviceSerial);

            // Get all users from local model
            $zktecoUsers = ZktecoUser::where('device_id', $device->id)->get();

            // Convert to UserRecord DTOs
            $users = [];
            foreach ($zktecoUsers as $zktecoUser) {
                $users[] = new UserRecord(
                    pin: (string) $zktecoUser->pin,
                    name: $zktecoUser->name ?? '',
                    privilege: (int) ($zktecoUser->privilege ?? 0),
                    card: $zktecoUser->card_number ?? '',
                    password: '', // Password is not stored in the model for security
                );
            }

            Log::debug('Retrieved users from device', [
                'device' => $deviceSerial,
                'count' => count($users),
            ]);

            return $users;
        } catch (\Exception $e) {
            Log::error('Failed to get all users from device', [
                'device' => $deviceSerial,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Push a user's photo to a device as a BIOPHOTO record.
     *
     * NOTE: the ZKTeco push protocol's exact BIOPHOTO field names/type code
     * vary a little across firmware generations. This uses the commonly
     * documented push-SDK format (Type=9 for a JPEG face photo) - verify
     * against your specific device model's protocol docs if photos don't
     * appear on-device after syncing.
     *
     * @param  string  $deviceSerial  The serial number of the device
     * @param  string  $pin  The user's PIN (matches the PIN used in addUserToDevice)
     * @param  string  $absolutePhotoPath  Absolute filesystem path to the source photo
     */
    private function queuePhotoCommand(string $deviceSerial, string $pin, string $absolutePhotoPath): void
    {
        try {
            if (! is_file($absolutePhotoPath)) {
                Log::warning('Photo file not found, skipping device photo sync', [
                    'device' => $deviceSerial,
                    'pin' => $pin,
                    'path' => $absolutePhotoPath,
                ]);

                return;
            }

            $jpegContents = $this->resizePhotoForDevice($absolutePhotoPath);

            if ($jpegContents === null) {
                Log::warning('Could not process photo for device sync', [
                    'device' => $deviceSerial,
                    'pin' => $pin,
                ]);

                return;
            }

            $encoded = base64_encode($jpegContents);

            $commandString = sprintf(
                "DATA UPDATE BIOPHOTO PIN=%s\tType=9\tSize=%d\tContent=%s",
                $pin,
                strlen($jpegContents),
                $encoded
            );

            $this->commandManager->queueCommand($deviceSerial, $commandString);

            Log::info('Photo command queued', [
                'device' => $deviceSerial,
                'pin' => $pin,
                'bytes' => strlen($jpegContents),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue photo command', [
                'device' => $deviceSerial,
                'pin' => $pin,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resize a photo down to a small square JPEG suitable for pushing to a
     * device (devices expect a small headshot, not a multi-megabyte upload).
     *
     * @return string|null The re-encoded JPEG binary contents, or null on failure
     */
    private function resizePhotoForDevice(string $absolutePath, int $maxDimension = 200): ?string
    {
        $imageInfo = @getimagesize($absolutePath);

        if ($imageInfo === false) {
            return null;
        }

        $source = match ($imageInfo['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($absolutePath),
            'image/png' => @imagecreatefrompng($absolutePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : false,
            default => false,
        };

        if (! $source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min($maxDimension / $width, $maxDimension / $height, 1);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        // Flatten transparency onto a white background - devices expect a plain JPEG.
        $white = imagecolorallocate($resized, 255, 255, 255);
        imagefill($resized, 0, 0, $white);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagejpeg($resized, null, 80);
        $contents = ob_get_clean();

        imagedestroy($source);
        imagedestroy($resized);

        return $contents !== false ? $contents : null;
    }
}
