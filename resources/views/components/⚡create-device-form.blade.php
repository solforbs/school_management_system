<?php

use App\Models\Devices;
use App\Models\User;
use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Flux\Flux;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Validate('nullable|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string')]
    public string $ip_address = '';

    #[Validate('string|required|max:255|unique:devices,serial_number')]
    public string $serial_number = '';

    public User $user;
    public $institutions;

    #[Validate('required|exists:institutions,id')]
    public $institution_id;

    public function mount()
    {
        $this->user = auth()->user();
        $this->institutions = $this->user->institution;
        $this->institution_id = currentInstitution()?->id ?? ($this->institutions->first()?->id ?? '');
    }

    public function createDevice()
    {
        $this->validate();

        $zktecoDevice = ZktecoDevice::firstOrCreate(
            ['serial_number' => $this->serial_number],
            [
                'name' => $this->name !== '' ? $this->name : $this->serial_number,
                'ip_address' => $this->ip_address !== '' ? $this->ip_address : null,
                'options' => [],
            ]
        );

        $device = new Devices();
        $device->institution_id = $this->institution_id;
        $device->zkteco_device_id = $zktecoDevice->id;
        $device->serial_number = $this->serial_number;
        $device->is_active = true;
        $device->save();

        session()->flash('success', 'Biometric device registered successfully.');
        $this->redirect(route('devices.index'), navigate: true);
    }
};
?>

<form wire:submit="createDevice" class="space-y-6">
    <div class="space-y-5">
        <div>
            <flux:heading size="md" class="font-semibold text-zinc-900 dark:text-white">Institution & Assignment</flux:heading>
            <flux:text class="text-xs text-zinc-500">Select which campus this attendance terminal belongs to.</flux:text>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <flux:select label="Select Institution" wire:model.live="institution_id" placeholder="Choose campus...">
                    @foreach ($institutions as $institution)
                        <option value="{{ $institution->id }}">{{ $institution->name }}</option>
                    @endforeach
                </flux:select>
                <flux:error for="institution_id" class="text-red-500 text-xs mt-1" />
            </div>
        </div>
    </div>

    <div class="border-t border-zinc-200 dark:border-zinc-800 pt-5 space-y-5">
        <div>
            <flux:heading size="md" class="font-semibold text-zinc-900 dark:text-white">Hardware Details</flux:heading>
            <flux:text class="text-xs text-zinc-500">Device serial number and identifier information.</flux:text>
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
                <flux:text class="text-[11px] text-zinc-400 mt-1">Found on the barcode sticker on the back of the ZKTeco hardware.</flux:text>
                <flux:error for="serial_number" class="text-red-500 text-xs mt-1" />
            </div>

            <div>
                <flux:input 
                    label="Device Name (Optional)" 
                    wire:model.live="name" 
                    placeholder="e.g. Main Gate Turnstile 01" 
                    icon="tag"
                />
                <flux:text class="text-[11px] text-zinc-400 mt-1">Friendly location name for your staff.</flux:text>
                <flux:error for="name" class="text-red-500 text-xs mt-1" />
            </div>

            <div class="sm:col-span-2">
                <flux:input 
                    label="Network IP Address (Optional)" 
                    wire:model.live="ip_address" 
                    placeholder="e.g. 192.168.1.120" 
                    icon="globe-alt" 
                    class="font-mono text-sm"
                />
                <flux:text class="text-[11px] text-zinc-400 mt-1">Leave blank if the terminal dynamically acquires IP or uses ADMS push.</flux:text>
                <flux:error for="ip_address" class="text-red-500 text-xs mt-1" />
            </div>
        </div>
    </div>

    <div class="border-t border-zinc-200 dark:border-zinc-800 pt-5 flex items-center justify-end gap-3">
        <flux:button wire:navigate href="{{ route('devices.index') }}" variant="ghost">
            Cancel
        </flux:button>
        <flux:button type="submit" variant="primary" icon="plus" class="shadow-sm">
            <span wire:loading.remove wire:target="createDevice">Register Device</span>
            <span wire:loading wire:target="createDevice">Registering...</span>
        </flux:button>
    </div>
</form>
