<x-layouts::app :title="__('Institution Details')">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        {{-- Page Header --}}
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-6">
            <div class="flex items-center gap-6">
                {{-- Logo --}}
                <div class="shrink-0">
                    <span class="inline-flex size-20 items-center justify-center rounded-2xl bg-white border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden p-2">
                        @if ($institution->logo)
                            <img src="{{ Storage::url($institution->logo) }}" alt="{{ $institution->name }}" class="w-full h-full object-contain">
                        @else
                            <div class="text-xl font-bold bg-gradient-to-r from-[#00A3FF] to-[#0047FF] bg-clip-text text-transparent">
                                {{ substr($institution->name, 0, 1) }}
                            </div>
                        @endif
                    </span>
                </div>
                
                {{-- Title & Badges --}}
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $institution->name }}</h1>
                        
                        {{-- Status Badges --}}
                        <flux:badge size="sm" :color="$institution->is_active ? 'emerald' : 'zinc'" class="font-semibold">
                            {{ $institution->is_active ? 'Active' : 'Inactive' }}
                        </flux:badge>
                        @if (!$institution->is_approved)
                            <flux:badge size="sm" color="amber" class="font-semibold">Pending Approval</flux:badge>
                        @endif
                    </div>
                    <div class="flex items-center gap-4 text-sm text-zinc-500">
                        <span class="flex items-center gap-1.5"><flux:icon icon="building-office-2" class="size-4" /> {{ $institution->type ?? 'Not Set' }}</span>
                        <span>&bull;</span>
                        <span class="flex items-center gap-1.5"><flux:icon icon="star" class="size-4" /> {{ $institution->plan ? $institution->plan->name : 'No Plan' }}</span>
                        <span>&bull;</span>
                        <span class="flex items-center gap-1.5"><flux:icon icon="clock" class="size-4" /> Updated {{ $institution->updated_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('institution.index') }}" wire:navigate.hover icon="arrow-left" variant="outline">
                    Back
                </flux:button>
                @if (institutionOwner($institution->owner->id))
                    <flux:button href="{{ route('institution.edit', $institution->id) }}" wire:navigate.hover icon="pencil-square" variant="primary">
                        Edit Details
                    </flux:button>
                @endif
            </div>
        </div>

        {{-- Content Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            {{-- Basics Card --}}
            <flux:card class="space-y-6">
                <div class="flex items-center gap-3 text-[#00A3FF]">
                    <flux:icon icon="building-office-2" class="size-5" />
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Basic Information</h2>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-y-6 gap-x-4 text-sm">
                    <div>
                        <dt class="font-medium text-zinc-500 uppercase tracking-wider text-xs mb-1">Institution Code</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100 font-medium">{{ $institution->code }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-500 uppercase tracking-wider text-xs mb-1">Education Level</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $institution->education_level ?? 'Not Set' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-500 uppercase tracking-wider text-xs mb-1">Timezone</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $institution->timezone ?? 'Africa/Nairobi' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-500 uppercase tracking-wider text-xs mb-1">Elective Subjects</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $institution->elective_subject_count ?? 0 }} or more</dd>
                    </div>
                </dl>
            </flux:card>

            {{-- Contact Card --}}
            <flux:card class="space-y-6">
                <div class="flex items-center gap-3 text-[#0047FF]">
                    <flux:icon icon="envelope" class="size-5" />
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Contact Details</h2>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-y-6 gap-x-4 text-sm">
                    <div class="min-w-0">
                        <dt class="font-medium text-zinc-500 uppercase tracking-wider text-xs mb-1">Official Email</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">
                            @if($institution->email)
                                <a href="mailto:{{ $institution->email }}" class="text-[#00A3FF] hover:underline break-all">{{ $institution->email }}</a>
                            @else
                                <span class="text-zinc-400">Not Set</span>
                            @endif
                        </dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="font-medium text-zinc-500 uppercase tracking-wider text-xs mb-1">Website URL</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">
                            @if($institution->website)
                                <a href="{{ $institution->website }}" target="_blank" class="text-[#00A3FF] hover:underline break-all">{{ $institution->website }}</a>
                            @else
                                <span class="text-zinc-400">Not Set</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-500 uppercase tracking-wider text-xs mb-1">Main Phone</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $institution->phone ?? 'Not Set' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-500 uppercase tracking-wider text-xs mb-1">Alternative Phone</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $institution->alternate_phone ?? 'Not Set' }}</dd>
                    </div>
                </dl>
            </flux:card>

            {{-- Location Card --}}
            <flux:card class="space-y-6 lg:col-span-2">
                <div class="flex items-center gap-3 text-[#00A3FF]">
                    <flux:icon icon="map-pin" class="size-5" />
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Location & Address</h2>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-y-6 gap-x-4 text-sm">
                    <div>
                        <dt class="font-medium text-zinc-500 uppercase tracking-wider text-xs mb-1">Country</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $institution->country ?? 'Kenya' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-500 uppercase tracking-wider text-xs mb-1">State / County</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $institution->county ?? 'Not Set' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-500 uppercase tracking-wider text-xs mb-1">City / Town</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $institution->city ?? 'Not Set' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-500 uppercase tracking-wider text-xs mb-1">Postal Address</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $institution->postal_address ?? 'Not Set' }}</dd>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4 border-t border-zinc-100 dark:border-white/5 pt-4 mt-2">
                        <dt class="font-medium text-zinc-500 uppercase tracking-wider text-xs mb-2">Physical Address & Directions</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100 bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-zinc-100 dark:border-white/10">
                            {{ $institution->physical_address ?: 'No physical address provided.' }}
                        </dd>
                    </div>
                </dl>
            </flux:card>

            {{-- Admin Controls Card --}}
            @hasrole('Admin')
            <flux:card class="space-y-6 lg:col-span-2">
                <div class="flex items-center gap-3 text-purple-600">
                    <flux:icon icon="adjustments-horizontal" class="size-5" />
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Administrative Details</h2>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-y-6 gap-x-4 text-sm">
                    <div>
                        <dt class="font-medium text-zinc-500 uppercase tracking-wider text-xs mb-1">Principal Name</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $institution->principal_name ?? 'Not Set' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-500 uppercase tracking-wider text-xs mb-1">Principal Phone</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100">{{ $institution->principal_phone ?? 'Not Set' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-500 uppercase tracking-wider text-xs mb-1">Subscription Expiry</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100 font-medium">
                            @if($institution->subscription_expires_at)
                                {{ \Carbon\Carbon::parse($institution->subscription_expires_at)->format('F d, Y') }}
                            @else
                                <span class="text-zinc-400">Never</span>
                            @endif
                        </dd>
                    </div>
                    <div class="sm:col-span-3 border-t border-zinc-100 dark:border-white/5 pt-4 mt-2">
                        <dt class="font-medium text-zinc-500 uppercase tracking-wider text-xs mb-2">Internal Notes</dt>
                        <dd class="text-zinc-900 dark:text-zinc-100 text-sm whitespace-pre-wrap">{{ $institution->notes ?: 'No additional notes.' }}</dd>
                    </div>
                </dl>
            </flux:card>
            @endhasrole
        </div>

    </div>
</x-layouts::app>
