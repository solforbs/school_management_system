<?php

namespace App\Http\Controllers;

use App\Models\Devices;
use App\Models\User;
use App\Services\ProfilePhotoResolver;
use App\Services\ZKTecoUserSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Student\Models\StudentDetails;

class SyncStudentToDeviceController extends Controller
{
    public function __construct(
        private readonly ZKTecoUserSyncService $syncService,
        private readonly ProfilePhotoResolver $photoResolver,
    ) {}

    /**
     * Reply to the sync trigger: a redirect back with a flash toast for the
     * student page, or JSON for a programmatic caller that asked for it.
     *
     * @param  array<string, mixed>  $data
     */
    private function respond(Request $request, bool $success, string $message, int $status = 200, array $data = []): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => $success, 'message' => $message, 'data' => $data], $status);
        }

        return redirect()->back()->with($success ? 'success' : 'error', $message);
    }

    /**
     * Manually sync a student to all active devices.
     */
    public function syncStudent(Request $request, $studentId)
    {
        $student = User::findOrFail($studentId);

        if (! $student->hasRole('Student')) {
            return $this->respond($request, false, 'That account is not a student.', 422);
        }

        $studentDetails = StudentDetails::where('student_id', $student->id)->first();

        if (! $studentDetails) {
            return $this->respond($request, false, 'This student has no profile to sync yet.', 404);
        }

        abort_unless(isAdmin() || $studentDetails->institution_id === currentInstitution()?->id, 403);

        $devices = Devices::where('institution_id', $studentDetails->institution_id)
            ->where('is_active', true)
            ->get();

        if ($devices->isEmpty()) {
            return $this->respond($request, false, 'No active hardware devices are set up for this school.', 404);
        }

        return $this->photoResolver->withLocalPath($studentDetails->profile_photo, function (?string $photoPath) use ($request, $student, $studentDetails, $devices) {
            $deviceUserData = $this->buildDeviceUserData($student, $studentDetails, $photoPath);

            Log::debug('Manual sync data', [
                'student_id' => $student->id,
                'pin' => $deviceUserData['pin'],
                'name' => $deviceUserData['name'],
                'card' => $deviceUserData['card'],
                'password' => $deviceUserData['password'] ? 'set' : 'not set',
                'photo' => $deviceUserData['photo_path'] ? 'set' : 'not set',
            ]);

            $results = [];
            $syncedCount = 0;

            foreach ($devices as $device) {
                try {
                    $exists = $this->syncService->userExistsOnDevice(
                        $device->serial_number,
                        (string) $student->id
                    );

                    if ($exists) {
                        $results[$device->serial_number] = [
                            'status' => 'exists',
                            'message' => 'User already exists on this device',
                        ];

                        continue;
                    }

                    $result = $this->syncService->addUserToDevice(
                        $device->serial_number,
                        $deviceUserData
                    );

                    if ($result) {
                        $syncedCount++;
                        $results[$device->serial_number] = [
                            'status' => 'success',
                            'message' => 'User synced successfully',
                        ];
                    } else {
                        $results[$device->serial_number] = [
                            'status' => 'failed',
                            'message' => 'Failed to sync user to device',
                        ];
                    }

                } catch (\Exception $e) {
                    Log::error('Manual sync failed for student', [
                        'student_id' => $student->id,
                        'device' => $device->serial_number,
                        'error' => $e->getMessage(),
                    ]);

                    $results[$device->serial_number] = [
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ];
                }
            }

            $existsCount = collect($results)->where('status', 'exists')->count();
            $total = $devices->count();

            // "Already on every device" is a success, not a failure - only
            // flip the flag negative when a sync was actually attempted and
            // failed everywhere.
            if ($syncedCount > 0 || $existsCount === $total) {
                $student->update([
                    'zkteco_synced' => true,
                    'zkteco_synced_at' => now(),
                    'zkteco_sync_error' => null,
                ]);
            } else {
                $student->update([
                    'zkteco_synced' => false,
                    'zkteco_sync_error' => 'Manual sync failed for all devices',
                ]);
            }

            $message = match (true) {
                $syncedCount > 0 => "Synced to {$syncedCount} of {$total} hardware device(s).",
                $existsCount === $total => "Already synced to all {$total} hardware device(s).",
                default => "Couldn't reach any hardware device. Check they're online and try again.",
            };

            return $this->respond(
                $request,
                $syncedCount > 0 || $existsCount === $total,
                $message,
                200,
                ['devices_synced' => $syncedCount, 'total_devices' => $total, 'results' => $results],
            );
        });
    }

    /**
     * Sync a student to a specific device.
     */
    public function syncStudentToDevice(Request $request, $studentId, $deviceSerial)
    {
        $student = User::findOrFail($studentId);

        if (! $student->hasRole('Student')) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a student',
            ], 422);
        }

        $studentDetails = StudentDetails::where('student_id', $student->id)->first();

        if (! $studentDetails) {
            return response()->json([
                'success' => false,
                'message' => 'Student profile not found',
            ], 404);
        }

        abort_unless(isAdmin() || $studentDetails->institution_id === currentInstitution()?->id, 403);

        // Check if device exists, is active, and belongs to the student's
        // own institution - otherwise a device serial from a different
        // school could be targeted directly.
        $device = Devices::where('serial_number', $deviceSerial)
            ->where('institution_id', $studentDetails->institution_id)
            ->where('is_active', true)
            ->first();

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found or inactive',
            ], 404);
        }

        return $this->photoResolver->withLocalPath($studentDetails->profile_photo, function (?string $photoPath) use ($student, $studentDetails, $device) {
            $deviceUserData = $this->buildDeviceUserData($student, $studentDetails, $photoPath);

            try {
                // Check if user exists
                $exists = $this->syncService->userExistsOnDevice(
                    $device->serial_number,
                    (string) $student->id
                );

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User already exists on this device',
                        'data' => [
                            'student_id' => $student->id,
                            'device_serial' => $device->serial_number,
                            'status' => 'exists',
                        ],
                    ]);
                }

                // Add user to device
                $result = $this->syncService->addUserToDevice(
                    $device->serial_number,
                    $deviceUserData
                );

                if ($result) {
                    // Update student sync status
                    $student->update([
                        'zkteco_synced' => true,
                        'zkteco_synced_at' => now(),
                        'zkteco_sync_error' => null,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Student synced successfully',
                        'data' => [
                            'student_id' => $student->id,
                            'device_serial' => $device->serial_number,
                            'pin' => $deviceUserData['pin'],
                            'status' => 'success',
                        ],
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to sync student to device',
                    ], 500);
                }
            } catch (\Exception $e) {
                Log::error('Manual sync failed', [
                    'student_id' => $student->id,
                    'device' => $device->serial_number,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error syncing student: '.$e->getMessage(),
                ], 500);
            }
        });
    }

    /**
     * Build the payload sent to ZKTecoUserSyncService::addUserToDevice().
     *
     * IMPORTANT: Password = admission_number, Card = student_number. Photo
     * (if the student has one on file) rides along in the same sync call so
     * PIN/name/card/password/photo always stay in lockstep on the device.
     */
    private function buildDeviceUserData(User $student, StudentDetails $studentDetails, ?string $photoPath): array
    {
        return [
            'pin' => (string) $student->id,
            'name' => $student->name,
            'privilege' => 0, // Normal user
            'card' => $studentDetails->student_number ?? '',
            'password' => $studentDetails->admission_number ?? '',
            'app_user_id' => $student->id,
            'photo_path' => $photoPath,
        ];
    }
}
