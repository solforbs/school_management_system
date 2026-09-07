<?php

use App\Models\Devices;
use App\Services\ClockSyncOutcome;
use App\Services\DeviceAttendanceLogRetention;
use App\Services\DeviceAttendanceLogRetentionOutcome;
use App\Services\DeviceClockSync;
use Athwari\LaravelZktecoAdms\Enums\CommandStatus;
use Athwari\LaravelZktecoAdms\Enums\UserPrivilege;
use Athwari\LaravelZktecoAdms\Exceptions\CommandQueueFullException;
use Athwari\LaravelZktecoAdms\Facades\ZktecoAdms;
use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Athwari\LaravelZktecoAdms\Models\ZktecoDeviceCommand;
use Athwari\LaravelZktecoAdms\Models\ZktecoUser;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * One page to operate a biometric terminal: settings/info, its own user
 * table, clock sync, on-device log clearing, and a read-only command queue.
 *
 * Everything here is push-protocol, not a live remote control - ADMS has no
 * synchronous read, so every action just queues a command the device only
 * picks up on its next poll. The UI is written to never imply otherwise.
 * Deliberately absent: anything about what the device has recorded - this
 * page manages hardware, never attendance data.
 */
new #[Title('Manage Device')] class extends Component
{
    use WithPagination;

    public Devices $device;

    #[Url]
    public string $tab = 'overview';

    public string $newPin = '';

    public string $newName = '';

    public string $newPrivilege = '0';

    public string $newCard = '';

    /** Keypad password for the added user - defaults to 1234, digits only, max 6. */
    public string $newPassword = '1234';

    public function mount(int $deviceId): void
    {
        $device = Devices::findOrFail($deviceId);

        abort_unless(
            auth()->user()->can('manage devices')
                && (isAdmin() || $device->institution_id === currentInstitution()?->id),
            403
        );

        $this->device = $device;
    }

    #[Computed]
    public function zktecoDevice(): ?ZktecoDevice
    {
        return $this->device->zktecoDevice;
    }

    #[Computed]
    public function users(): Collection
    {
        $zktecoDevice = $this->zktecoDevice;

        return $zktecoDevice
            ? ZktecoUser::where('device_id', $zktecoDevice->id)->orderBy('name')->get()
            : collect();
    }

    #[Computed]
    public function commands(): LengthAwarePaginator
    {
        $zktecoDevice = $this->zktecoDevice;

        if (! $zktecoDevice) {
            return new LengthAwarePaginator([], 0, 15);
        }

        return ZktecoDeviceCommand::where('device_id', $zktecoDevice->id)
            ->latest('queued_at')
            ->paginate(15);
    }

    #[Computed]
    public function hasPendingClockSync(): bool
    {
        return app(DeviceClockSync::class)->hasPendingSync($this->device);
    }

    /**
     * Every action below shares the same shape: queue a command, then tell
     * the operator honestly that it is queued, not done - the device only
     * applies it once it next checks in.
     */
    private function queued(string $what): void
    {
        session()->flash('status', "{$what} — the device will pick it up on its next check-in (~30s if online).");
    }

    public function refreshInfo(): void
    {
        $this->runCommand(fn () => ZktecoAdms::sendInfoCommand($this->device->serial_number), 'Info request queued');
    }

    public function checkConnection(): void
    {
        $this->runCommand(fn () => ZktecoAdms::sendCheckCommand($this->device->serial_number), 'Connection check queued');
    }

    public function reboot(): void
    {
        $this->runCommand(fn () => ZktecoAdms::sendRebootCommand($this->device->serial_number), 'Reboot queued');
    }

    public function syncTime(): void
    {
        $outcome = app(DeviceClockSync::class)->sync($this->device);

        session()->flash(
            $outcome === ClockSyncOutcome::Queued ? 'status' : 'error',
            match ($outcome) {
                ClockSyncOutcome::Queued => 'Clock sync queued — the device will set itself on its next check-in.',
                ClockSyncOutcome::NeverConnected => 'This device has never connected, so there is nothing to send a clock to yet.',
                ClockSyncOutcome::QueueFull => 'This device has too many commands waiting already. Let it check in, then try again.',
            }
        );

        unset($this->hasPendingClockSync);
    }

    public function clearDeviceLogs(): void
    {
        $outcome = app(DeviceAttendanceLogRetention::class)->clearNow($this->device);

        session()->flash(
            $outcome === DeviceAttendanceLogRetentionOutcome::Queued ? 'status' : 'error',
            match ($outcome) {
                DeviceAttendanceLogRetentionOutcome::Queued => 'Clearing all attendance logs stored on this device — your records are unaffected, they stay in the database.',
                DeviceAttendanceLogRetentionOutcome::NeverConnected => 'This device has never connected, so there is nothing to clear yet.',
                DeviceAttendanceLogRetentionOutcome::QueueFull => 'This device has too many commands waiting already. Let it check in, then try again.',
                default => 'Could not queue the clear right now.',
            }
        );

        unset($this->zktecoDevice);
    }

    public function refreshUsers(): void
    {
        $this->runCommand(fn () => ZktecoAdms::sendQueryUsersCommand($this->device->serial_number), 'User list refresh queued');
    }

    public function addUser(): void
    {
        $this->validate([
            'newPin' => ['required', 'string', 'max:20'],
            'newName' => ['required', 'string', 'max:100'],
            'newPrivilege' => ['required', 'in:0,14'],
            'newCard' => ['nullable', 'string', 'max:20'],
            'newPassword' => ['nullable', 'string', 'max:6'],
        ]);

        $this->runCommand(fn () => ZktecoAdms::sendUserAddCommand(
            $this->device->serial_number,
            $this->newPin,
            $this->newName,
            (int) $this->newPrivilege,
            $this->newCard,
            $this->newPassword,
        ), "User \"{$this->newName}\" queued to be added to the device");

        $this->reset('newPin', 'newName', 'newPrivilege', 'newCard');
        $this->newPassword = '1234';
    }

    public function deleteDeviceUser(string $pin): void
    {
        $this->runCommand(
            fn () => ZktecoAdms::sendUserDeleteCommand($this->device->serial_number, $pin),
            "Removal of user PIN {$pin} queued on the device"
        );
    }

    private function runCommand(Closure $send, string $successMessage): void
    {
        try {
            $send();
            $this->queued($successMessage);
        } catch (CommandQueueFullException) {
            session()->flash('error', 'This device has too many commands waiting already. Let it check in, then try again.');
        }
    }
}; ?>

@php
    $zktecoDevice = $this->zktecoDevice;
    $isOnline = $zktecoDevice?->isOnline() ?? false;
@endphp

<div class="flex flex-col gap-6 w-full max-w-6xl mx-auto p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <flux:button href="{{ route('devices.index') }}" wire:navigate.hover icon="arrow-left" variant="ghost" size="sm" />
                <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                    {{ $zktecoDevice?->name ?: 'Biometric Attendance Device' }}
                </flux:heading>
                @if ($isOnline)
                    <flux:badge color="emerald" size="sm">
                        <span class="size-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                        Online
                    </flux:badge>
                @else
                    <flux:badge color="zinc" size="sm">Offline</flux:badge>
                @endif
            </div>
            <flux:text class="text-zinc-500 text-sm mt-1 font-mono">SN: {{ $device->serial_number }}</flux:text>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200/50 bg-emerald-50/50 dark:bg-emerald-500/10 dark:border-emerald-500/20 p-4 flex items-start shadow-sm">
            <flux:icon icon="check-circle" class="size-5 text-emerald-500 mr-3 mt-0.5 shrink-0" />
            <div class="text-sm font-medium text-emerald-800 dark:text-emerald-400">{{ session('status') }}</div>
        </div>
    @endif
    @if (session('error'))
        <div class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm">
            <flux:icon icon="exclamation-triangle" class="size-5 text-red-500 mr-3 mt-0.5 shrink-0" />
            <div class="text-sm font-medium text-red-800 dark:text-red-400">{{ session('error') }}</div>
        </div>
    @endif

    @unless ($zktecoDevice)
        <div class="bg-amber-50 dark:bg-amber-500/10 border-l-4 border-amber-500 p-4 rounded-r-xl">
            <p class="text-sm font-medium text-amber-800 dark:text-amber-400">
                This device has never connected to the server, so there's nothing to manage yet — every action below needs the physical unit to have checked in at least once.
            </p>
        </div>
    @endunless

    {{-- Tabs --}}
    <div class="flex flex-wrap gap-1 border-b border-zinc-200 dark:border-zinc-800">
        @foreach (['overview' => 'Settings', 'users' => 'Users', 'time' => 'Time', 'logs' => 'Attendance Logs', 'commands' => 'Command Queue'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors {{ $tab === $key ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Overview / Settings --}}
    @if ($tab === 'overview')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div><span class="text-zinc-400">Name</span><div class="font-medium">{{ $zktecoDevice?->name ?? '—' }}</div></div>
                <div><span class="text-zinc-400">IP Address</span><div class="font-mono">{{ $zktecoDevice?->ip_address ?? '—' }}</div></div>
                <div><span class="text-zinc-400">Firmware</span><div class="font-medium">{{ $zktecoDevice?->firmware_version ?? '—' }}</div></div>
                <div><span class="text-zinc-400">Device Type</span><div class="font-medium">{{ $zktecoDevice?->device_type ?? '—' }}</div></div>
                <div><span class="text-zinc-400">Timezone</span><div class="font-medium">{{ $zktecoDevice?->getEffectiveTimezone() ?? '—' }}</div></div>
                <div>
                    <span class="text-zinc-400">Last known as of</span>
                    <div class="font-medium">{{ $zktecoDevice?->last_activity_at?->diffForHumans() ?? 'never' }}</div>
                </div>
            </div>
            <flux:text class="text-xs text-zinc-400">
                These values are only as fresh as the device's last check-in — ADMS has no live remote read, every button below queues a command for the terminal to pick up next time it polls.
            </flux:text>
            <div class="flex flex-wrap gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button wire:click="refreshInfo" icon="arrow-path" variant="ghost" :disabled="! $zktecoDevice">Refresh Info</flux:button>
                <flux:button wire:click="checkConnection" icon="signal" variant="ghost" :disabled="! $zktecoDevice">Check Connection</flux:button>
                <flux:modal.trigger name="confirm-reboot">
                    <flux:button icon="power" variant="danger" :disabled="! $zktecoDevice">Reboot Device</flux:button>
                </flux:modal.trigger>
            </div>
        </div>

        <flux:modal name="confirm-reboot" class="max-w-md">
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Reboot this terminal?</flux:heading>
                    <flux:subheading>It will restart on its next check-in and be briefly unreachable.</flux:subheading>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="filled">Cancel</flux:button></flux:modal.close>
                    <flux:modal.close><flux:button variant="danger" wire:click="reboot">Reboot</flux:button></flux:modal.close>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Users --}}
    @if ($tab === 'users')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 space-y-6">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">Device Users</flux:heading>
                <flux:button wire:click="refreshUsers" icon="arrow-path" variant="ghost" size="sm" :disabled="! $zktecoDevice">Refresh from Device</flux:button>
            </div>

            <form wire:submit="addUser" class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
                <flux:input wire:model="newPin" label="PIN" placeholder="1024" />
                <flux:input wire:model="newName" label="Name" placeholder="Jane Doe" />
                <flux:select wire:model="newPrivilege" label="Privilege">
                    <flux:select.option value="0">User</flux:select.option>
                    <flux:select.option value="14">Admin</flux:select.option>
                </flux:select>
                <flux:input wire:model="newCard" label="Card (optional)" />
                <flux:input wire:model="newPassword" label="Password" placeholder="1234"
                    description="Keypad password, digits only (max 6). Defaults to 1234." />
                <div class="sm:col-span-5">
                    <flux:button type="submit" icon="plus" variant="primary" :disabled="! $zktecoDevice">Add User to Device</flux:button>
                </div>
            </form>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>PIN</flux:table.column>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Privilege</flux:table.column>
                    <flux:table.column>Enabled</flux:table.column>
                    <flux:table.column>Actions</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->users as $user)
                        <flux:table.row>
                            <flux:table.cell class="font-mono">{{ $user->pin }}</flux:table.cell>
                            <flux:table.cell>{{ $user->name }}</flux:table.cell>
                            <flux:table.cell>{{ $user->privilege === UserPrivilege::Admin ? 'Admin' : 'User' }}</flux:table.cell>
                            <flux:table.cell>{{ $user->is_enabled ? 'Yes' : 'No' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:button size="sm" variant="ghost" icon="trash" class="text-red-500"
                                    wire:click="deleteDeviceUser('{{ $user->pin }}')"
                                    wire:confirm="Remove this user from the device?">
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="text-center text-zinc-400 py-6">
                                No users known for this device yet — try "Refresh from Device".
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    {{-- Time --}}
    @if ($tab === 'time')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 space-y-4">
            <flux:heading size="lg">Device Clock</flux:heading>
            <flux:text class="text-zinc-500 text-sm">
                Sets the terminal's clock to the server's own time ({{ config('app.timezone') }}), so every punch it records lands in the right window for check-in/check-out/overtime classification.
            </flux:text>
            @if ($this->hasPendingClockSync)
                <flux:badge color="amber" size="sm">A clock sync is already queued, waiting for the device to check in</flux:badge>
            @endif
            <div>
                <flux:button wire:click="syncTime" icon="clock" variant="primary" :disabled="! $zktecoDevice">Sync Clock Now</flux:button>
            </div>
        </div>
    @endif

    {{-- Attendance log clearing --}}
    @if ($tab === 'logs')
        <div class="bg-white dark:bg-zinc-900 border border-red-200 dark:border-red-500/20 rounded-xl p-6 space-y-4">
            <flux:heading size="lg" class="text-red-700 dark:text-red-400">Clear Attendance Logs on Device</flux:heading>
            <flux:text class="text-zinc-500 text-sm">
                Wipes the attendance history stored in this terminal's own memory. This does not affect your records —
                every scan is already saved in your database and stays there. This exists purely to keep the device's
                limited onboard storage from filling up; it is normally cleared automatically once a week.
            </flux:text>
            <flux:text class="text-xs text-zinc-400">
                Last cleared: {{ $zktecoDevice?->attendance_logs_cleared_at?->diffForHumans() ?? 'never' }}
            </flux:text>
            <flux:modal.trigger name="confirm-clear-logs">
                <flux:button icon="trash" variant="danger" :disabled="! $zktecoDevice">Clear All Attendance Logs on Device</flux:button>
            </flux:modal.trigger>
        </div>

        <flux:modal name="confirm-clear-logs" class="max-w-md">
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Clear all attendance logs on this device?</flux:heading>
                    <flux:subheading>Your records are safe in the database — this only wipes the terminal's own memory.</flux:subheading>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="filled">Cancel</flux:button></flux:modal.close>
                    <flux:modal.close><flux:button variant="danger" wire:click="clearDeviceLogs">Clear Device Logs</flux:button></flux:modal.close>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Command queue --}}
    @if ($tab === 'commands')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Command</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Queued</flux:table.column>
                    <flux:table.column>Sent</flux:table.column>
                    <flux:table.column>Acknowledged</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->commands as $command)
                        <flux:table.row>
                            <flux:table.cell class="font-mono text-xs max-w-xs truncate">{{ $command->command_content }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="match($command->status) {
                                    CommandStatus::Pending => 'amber',
                                    CommandStatus::Sent => 'sky',
                                    CommandStatus::Acknowledged => 'emerald',
                                    CommandStatus::Failed => 'red',
                                }">
                                    {{ ucfirst($command->status->value) }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-xs">{{ $command->queued_at?->format('M j, H:i') }}</flux:table.cell>
                            <flux:table.cell class="text-xs">{{ $command->sent_at?->format('M j, H:i') ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="text-xs">{{ $command->acknowledged_at?->format('M j, H:i') ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="text-center text-zinc-400 py-6">No commands queued for this device yet.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
            @if ($this->commands->hasPages())
                <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">{{ $this->commands->links() }}</div>
            @endif
        </div>
    @endif
</div>
