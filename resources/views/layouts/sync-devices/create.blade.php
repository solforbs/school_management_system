<x-layouts::app :title="__('Enroll Offline Device')">
    <div class="flex flex-col gap-6 w-full max-w-3xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-zinc-500 mb-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200">Dashboard</a>
                    <span>/</span>
                    <a href="{{ route('sync-devices.index') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200">Sync Devices</a>
                    <span>/</span>
                    <span class="text-zinc-800 dark:text-zinc-200 font-medium">Enroll Device</span>
                </div>
                <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                    Enroll Offline Sync Terminal
                </flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Mint a dedicated pairing token for a laptop or tablet to synchronize records offline.
                </flux:text>
            </div>
            
            <div>
                <flux:button href="{{ route('sync-devices.index') }}" icon="arrow-left" variant="ghost" class="hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    Back to Devices
                </flux:button>
            </div>
        </div>

        {{-- Error Alerts --}}
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 dark:bg-red-950/30 dark:border-red-800/40 p-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mt-0.5 shrink-0" />
                    <div>
                        <div class="text-sm font-semibold text-red-800 dark:text-red-400">Please correct the following errors:</div>
                        <ul class="list-disc list-inside text-xs text-red-700 dark:text-red-300 mt-1 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- Form Container --}}
        <div class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 sm:p-8">
            <form action="{{ route('sync-devices.store') }}" method="POST" class="space-y-6">
                @csrf

                {{-- Device Identity --}}
                <div class="space-y-4">
                    <div>
                        <flux:heading size="md" class="font-semibold text-zinc-900 dark:text-white">Device Information</flux:heading>
                        <flux:text class="text-xs text-zinc-500">Provide an identifiable name and target operating system.</flux:text>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <flux:input 
                                type="text" 
                                name="name" 
                                label="Device Name *" 
                                value="{{ old('name') }}" 
                                placeholder="e.g. Bursar's Laptop" 
                                icon="tag"
                                required 
                            />
                            <flux:text class="text-[11px] text-zinc-400 mt-1">Identifies this specific machine in audit logs.</flux:text>
                        </div>

                        <div>
                            <flux:select name="platform" label="Platform Architecture *" required>
                                @foreach (['desktop' => 'Desktop (Windows / macOS)', 'android' => 'Android (Tablet / Mobile)', 'ios' => 'iOS (iPad / iPhone)'] as $value => $label)
                                    <option value="{{ $value }}" {{ old('platform', 'desktop') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </flux:select>
                            <flux:text class="text-[11px] text-zinc-400 mt-1">Operating system of the physical terminal.</flux:text>
                        </div>
                    </div>
                </div>

                {{-- Operator Authorization --}}
                <div class="border-t border-zinc-200 dark:border-zinc-800 pt-5 space-y-4">
                    <div>
                        <flux:heading size="md" class="font-semibold text-zinc-900 dark:text-white">Authorized Account</flux:heading>
                        <flux:text class="text-xs text-zinc-500">The offline client inherits the access scope of the assigned operator.</flux:text>
                    </div>

                    <div>
                        <flux:select name="user_id" label="Syncs As Account *" required placeholder="Select authorized operator...">
                            <option value="">Select Account</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" {{ old('user_id') == $account->id ? 'selected' : '' }}>
                                    {{ $account->name }} ({{ $account->getRoleNames()->first() ?? 'Staff' }})
                                </option>
                            @endforeach
                        </flux:select>
                        <flux:text class="text-[11px] text-zinc-400 mt-1">The offline terminal can only sync records accessible to this user.</flux:text>
                    </div>
                </div>

                {{-- Security Callout --}}
                <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 dark:bg-indigo-950/20 dark:border-indigo-900/40 p-4 flex items-start gap-3">
                    <flux:icon icon="shield-check" class="size-5 text-indigo-600 dark:text-indigo-400 mt-0.5 shrink-0" />
                    <div class="text-xs text-zinc-600 dark:text-zinc-300 leading-relaxed">
                        <strong class="font-semibold text-zinc-900 dark:text-white">Single-Machine Pairing:</strong> Enroll one token per physical computer. If a laptop is lost or stolen, its access token can be revoked instantly without affecting any other devices.
                    </div>
                </div>

                {{-- Form Actions --}}
                <div class="border-t border-zinc-200 dark:border-zinc-800 pt-5 flex items-center justify-end gap-3">
                    <flux:button href="{{ route('sync-devices.index') }}" variant="ghost">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary" icon="key" class="shadow-sm">
                        Generate Pairing Token
                    </flux:button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
