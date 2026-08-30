<?php

use App\Models\Devices;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    public Devices $device;

    #[Validate('nullable|string|max:255')]
    public $name;

    #[Validate('nullable|string')]
    public $ip_address;

    #[Validate('string|required|max:255')]
    public $serial_number;

    #[Validate('boolean')]
    public $is_active;

    public function mount(Devices $device)
    {
        $this->device = $device;
        $this->name = $device->zktecoDevice?->name;
        $this->ip_address = $device->zktecoDevice?->ip_address;
        $this->serial_number = $device->serial_number;
        $this->is_active = (bool) $device->is_active;
    }

    public function updateDevice()
    {
        $this->validate([
            'name' => 'nullable|string|max:255',
            'ip_address' => 'nullable|string',
            'serial_number' => [
                'required', 'string', 'max:255',
                Rule::unique('devices', 'serial_number')->ignore($this->device->id),
            ],
            'is_active' => 'boolean',
        ]);

        $this->device->serial_number = $this->serial_number;
        $this->device->is_active = $this->is_active;
        $this->device->linkZktecoDevice($this->name ?: null, $this->ip_address ?: null);

        session()->flash('success', 'Biometric device updated successfully.');
        $this->redirect(route('devices.index'), navigate: true);
    }
};
?>

<form wire:submit="updateDevice" class="space-y-6">
    <div class="space-y-5">
        <div>
            <flux:heading size="md" class="font-semibold text-zinc-900 dark:text-white">Device Information</flux:heading>
            <flux:text class="text-xs text-zinc-500">Update hardware serial number and custom location label.</flux:text>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <flux:input 
                    label="Serial Number *" 
                    wire:model.live="serial_number" 
                    placeholder="e.g. BKT721098492" 
                    icon="qr-code" 
                    class="font-mono text-sm"
                />
                <flux:text class="text-[11px] text-zinc-400 mt-1">Unique hardware serial identifier.</flux:text>
                <flux:error for="serial_number" class="text-red-500 text-xs mt-1" />
            </div>

            <div>
                <flux:input 
                    label="Device Name" 
                    wire:model.live="name" 
                    placeholder="e.g. Science Lab Turnstile" 
                    icon="tag"
                />
                <flux:text class="text-[11px] text-zinc-400 mt-1">Display name shown across attendance reports.</flux:text>
                <flux:error for="name" class="text-red-500 text-xs mt-1" />
            </div>

            <div class="sm:col-span-2">
                <flux:input 
                    label="Network IP Address" 
                    wire:model.live="ip_address" 
                    placeholder="e.g. 192.168.1.120" 
                    icon="globe-alt" 
                    class="font-mono text-sm"
                />
                <flux:text class="text-[11px] text-zinc-400 mt-1">Local network IP configured on the terminal.</flux:text>
                <flux:error for="ip_address" class="text-red-500 text-xs mt-1" />
            </div>
        </div>
    </div>

    <div class="border-t border-zinc-200 dark:border-zinc-800 pt-5 space-y-4">
        <div>
            <flux:heading size="md" class="font-semibold text-zinc-900 dark:text-white">Terminal Status</flux:heading>
            <flux:text class="text-xs text-zinc-500">Enable or disable attendance syncing from this terminal.</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 p-4 bg-zinc-50/50 dark:bg-zinc-800/30 flex items-center justify-between">
            <div>
                <div class="font-medium text-sm text-zinc-900 dark:text-white">Active Terminal</div>
                <div class="text-xs text-zinc-500">When enabled, attendance logs received from this unit will be processed.</div>
            </div>
            <flux:switch wire:model.live="is_active" />
        </div>
    </div>

    <div class="border-t border-zinc-200 dark:border-zinc-800 pt-5 flex items-center justify-end gap-3">
        <flux:button wire:navigate href="{{ route('devices.index') }}" variant="ghost">
            Cancel
        </flux:button>
        <flux:button type="submit" variant="primary" icon="check" class="shadow-sm">
            <span wire:loading.remove wire:target="updateDevice">Save Changes</span>
            <span wire:loading wire:target="updateDevice">Saving...</span>
        </flux:button>
    </div>
</form>
