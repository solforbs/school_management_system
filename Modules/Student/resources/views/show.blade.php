@php
    $details = $studentDetails ?? $student->studentUserDetails;
    $initials = strtoupper(substr($student->name, 0, 2));
    $admNo = $details?->admission_number ?? 'ADM-' . str_pad($student->id, 4, '0', STR_PAD_LEFT);
    $className = $details?->schoolClass?->name ?? 'Unassigned Class';
    $classLevel = $details?->schoolClass?->level ?? 'General';
    $guardianName = $details?->guardian_name ?? $details?->parent_name ?? 'Parent / Guardian';
    $guardianPhone = $details?->guardian_phone ?? $details?->parent_phone ?? $details?->phone;
    $guardianEmail = $details?->guardian_email ?? $details?->parent_email;
    $status = $details?->enrollment_status ?? ($details?->is_active ? 'active' : 'inactive');
@endphp

<x-layouts::app :title="__('Student Profile - ' . $student->name)">
    <div class="flex flex-col gap-8 w-full max-w-6xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Navigation & Breadcrumbs --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                    <a href="{{ route('student.index') }}" wire:navigate.hover class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-500 hover:text-zinc-900 transition">
                        <flux:icon icon="arrow-left" class="size-3.5" />
                        <span>Back to Student Directory</span>
                    </a>
                    <span class="text-xs text-zinc-300">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">Student Dossier</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900"><span translate="no">{{ $student->name }}</span></flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Official learner profile, academic enrollment, guardian contacts, and medical history.
                </flux:text>
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto">
                @if($guardianPhone)
                    <a href="tel:{{ $guardianPhone }}" class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-800 text-xs font-bold transition">
                        <flux:icon icon="phone" class="size-3.5 text-zinc-500" />
                        <span>Call Guardian</span>
                    </a>
                @endif
                @can('edit student')
                    <flux:button href="{{ route('student.edit', $student->id) }}" wire:navigate.hover icon="pencil-square" variant="primary" class="font-semibold shadow-sm flex-1 sm:flex-initial justify-center">
                        Edit Profile
                    </flux:button>
                @endcan
            </div>
        </div>

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <flux:icon icon="check-circle" class="size-5 text-emerald-600 shrink-0" />
                    <span class="text-sm font-semibold text-emerald-900">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        {{-- Executive Identity Banner --}}
        <div class="bg-white rounded-3xl p-5 sm:p-8 border border-zinc-200/80 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-5 sm:gap-6">
            <div class="flex items-start sm:items-center gap-4">
                @if($details?->profile_photo && Storage::disk('public')->exists($details->profile_photo))
                    <img src="{{ asset('storage/' . $details->profile_photo) }}" alt="{{ $student->name }}" 
                        class="size-16 sm:size-20 rounded-2xl object-cover border border-zinc-200 shadow-sm shrink-0">
                @else
                    <div class="size-16 sm:size-20 rounded-2xl bg-zinc-900 text-white flex items-center justify-center font-black text-xl sm:text-2xl shadow-sm shrink-0">
                        {{ $initials }}
                    </div>
                @endif

                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-lg sm:text-xl font-black text-zinc-900 tracking-tight truncate"><span translate="no">{{ $student->name }}</span></h2>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold whitespace-nowrap shrink-0
                            {{ $status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                               ($status === 'suspended' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-zinc-100 text-zinc-700 border border-zinc-200') }}">
                            {{ ucfirst($status) }}
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 mt-1 font-mono">
                        <span class="px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-800 font-bold border border-zinc-200/80 whitespace-nowrap">{{ $admNo }}</span>
                        <span class="text-zinc-300">&bull;</span>
                        <span class="font-sans font-semibold text-zinc-700 truncate">{{ $className }}</span>
                        <span class="text-zinc-300">&bull;</span>
                        <span class="font-sans text-zinc-500 truncate"><span translate="no">{{ $student->email }}</span></span>
                    </div>
                </div>
            </div>

            <div class="flex sm:flex-col items-start sm:items-end justify-between w-full sm:w-auto pt-3 sm:pt-0 border-t sm:border-t-0 border-zinc-100 text-xs">
                <span class="text-zinc-400">Enrolled Since</span>
                <span class="font-bold text-zinc-900 mt-0.5">{{ $student->created_at->format('F d, Y') }}</span>
            </div>
        </div>

        {{-- 2-Column Responsive Dossier --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left Column (Broad: Personal, Guardian, Medical) --}}
            <div class="lg:col-span-2 flex flex-col gap-6">

                {{-- Personal & Contact Information Card --}}
                <div class="bg-white rounded-3xl p-5 sm:p-6 border border-zinc-200/80 shadow-sm space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b border-zinc-100">
                        <flux:icon icon="user" class="size-4 text-zinc-500" />
                        <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-700">Personal & Demographic Details</h3>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-5 text-xs">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Date of Birth</span>
                            <div class="font-bold text-zinc-900">
                                {{ $details?->date_of_birth ? \Carbon\Carbon::parse($details->date_of_birth)->format('M d, Y') : 'Not Recorded' }}
                            </div>
                            @if($details?->date_of_birth)
                                <span class="text-zinc-400 text-[11px]">{{ \Carbon\Carbon::parse($details->date_of_birth)->age }} years old</span>
                            @endif
                        </div>

                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Gender</span>
                            <div class="font-bold text-zinc-900 capitalize">{{ $details?->gender ?? 'Unspecified' }}</div>
                        </div>

                        <div class="col-span-2 sm:col-span-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Direct Phone</span>
                            <div class="font-bold text-zinc-900 font-mono"><span translate="no">{{ $details?->phone ?: 'None Provided' }}</span></div>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-zinc-100 text-xs">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Residential Address</span>
                        <p class="text-zinc-800 font-medium">
                            {{ $details?->address ?: 'No physical address logged' }}
                            @if($details?->city || $details?->state || $details?->country)
                                &mdash; {{ implode(', ', array_filter([$details->city, $details->state, $details->country])) }}
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Guardian & Emergency Contact Card --}}
                <div class="bg-white rounded-3xl p-6 border border-zinc-200/80 shadow-sm space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b border-zinc-100">
                        <flux:icon icon="user-group" class="size-4 text-zinc-500" />
                        <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-700">Primary Guardian & Emergency Contact</h3>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-5 text-xs">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Guardian Name</span>
                            <div class="font-bold text-zinc-900 truncate">{{ $guardianName }}</div>
                            <span class="text-zinc-500 text-[11px]">{{ $details?->guardian_relationship ?? 'Parent' }}</span>
                        </div>

                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Guardian Phone</span>
                            <div class="font-bold text-zinc-900 font-mono truncate">
                                @if($guardianPhone)
                                    <a href="tel:{{ $guardianPhone }}" class="hover:text-blue-600 transition">{{ $guardianPhone }}</a>
                                @else
                                    <span class="text-zinc-400">None Provided</span>
                                @endif
                            </div>
                        </div>

                        <div class="col-span-2 sm:col-span-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Guardian Email</span>
                            <div class="font-bold text-zinc-900 truncate">
                                @if($guardianEmail)
                                    <a href="mailto:{{ $guardianEmail }}" class="hover:text-blue-600 transition">{{ $guardianEmail }}</a>
                                @else
                                    <span class="text-zinc-400">None Provided</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($details?->parent_occupation)
                        <div class="pt-3 border-t border-zinc-100 text-xs">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Guardian Occupation</span>
                            <span class="text-zinc-800 font-medium">{{ $details->parent_occupation }}</span>
                        </div>
                    @endif
                </div>

                {{-- Medical & Special Accommodations Card --}}
                <div class="bg-white rounded-3xl p-6 border border-zinc-200/80 shadow-sm space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b border-zinc-100">
                        <flux:icon icon="heart" class="size-4 text-zinc-500" />
                        <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-700">Health, Allergies & Special Needs</h3>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 text-xs">
                        <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-100">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Medical Conditions</span>
                            <p class="text-zinc-800 font-medium {{ !$details?->medical_conditions ? 'text-zinc-400 italic' : '' }}">
                                {{ $details?->medical_conditions ?: 'None reported' }}
                            </p>
                        </div>

                        <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-100">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Allergies</span>
                            <p class="text-zinc-800 font-medium {{ !$details?->allergies ? 'text-zinc-400 italic' : '' }}">
                                {{ $details?->allergies ?: 'None reported' }}
                            </p>
                        </div>

                        <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-100">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Special Needs</span>
                            <p class="text-zinc-800 font-medium {{ !$details?->special_needs ? 'text-zinc-400 italic' : '' }}">
                                {{ $details?->special_needs ?: 'None reported' }}
                            </p>
                        </div>
                    </div>

                    @if($details?->notes)
                        <div class="p-4 rounded-2xl bg-zinc-50 border border-zinc-100 text-xs">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Administrative Notes</span>
                            <p class="text-zinc-800 font-medium whitespace-pre-line leading-relaxed">&ldquo;{{ $details->notes }}&rdquo;</p>
                        </div>
                    @endif
                </div>

            </div>

            {{-- Right Column (Narrow: Academic Profile, Biometric Sync, Quick Links) --}}
            <div class="flex flex-col gap-6">

                {{-- Academic Assignment Card --}}
                <div class="bg-white rounded-3xl p-6 border border-zinc-200/80 shadow-sm space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b border-zinc-100">
                        <flux:icon icon="academic-cap" class="size-4 text-zinc-500" />
                        <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-700">Academic Placement</h3>
                    </div>

                    <div class="space-y-3.5 text-xs">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Enrolled Institution</span>
                            <strong class="text-zinc-900 font-bold text-sm"><span translate="no">{{ $details?->institution?->name ?? currentInstitution()?->name ?? __('Not Assigned') }}</span></strong>
                        </div>

                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Current Class Stream</span>
                            <div class="flex items-center gap-2">
                                <strong class="text-zinc-900 font-bold">{{ $className }}</strong>
                                <span class="px-2 py-0.5 rounded bg-zinc-100 text-zinc-700 font-semibold text-[11px] border border-zinc-200/60">{{ $classLevel }}</span>
                            </div>
                        </div>

                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Official Admission Number</span>
                            <span class="font-mono font-bold text-zinc-900 text-xs bg-zinc-50 px-2.5 py-1 rounded-lg border border-zinc-200 inline-block">{{ $admNo }}</span>
                        </div>
                    </div>
                </div>

                {{-- Biometric Hardware Sync Card --}}
                <div class="bg-white rounded-3xl p-6 border border-zinc-200/80 shadow-sm space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                        <div class="flex items-center gap-2">
                            <flux:icon icon="finger-print" class="size-4 text-zinc-500" />
                            <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-700">Biometric Sync</h3>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold
                            {{ $student->zkteco_synced ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-zinc-100 text-zinc-600 border border-zinc-200' }}">
                            <span class="size-1.5 rounded-full {{ $student->zkteco_synced ? 'bg-emerald-500' : 'bg-zinc-400' }}"></span>
                            {{ $student->zkteco_synced ? 'Synced' : 'Not Synced' }}
                        </span>
                    </div>

                    <div class="text-xs text-zinc-500 space-y-2">
                        <p>ZKTeco terminal authentication payload credentials:</p>
                        <div class="p-3 rounded-xl bg-zinc-50 border border-zinc-100 font-mono text-[11px] text-zinc-800 space-y-1">
                            <div>PIN: <strong>{{ $student->id }}</strong></div>
                            <div>Card: <strong>{{ $details?->student_number ?: 'N/A' }}</strong></div>
                            @if($student->zkteco_synced_at)
                                <div class="text-[10px] text-zinc-400 pt-1 border-t border-zinc-200/60">
                                    Last Synced: {{ \Carbon\Carbon::parse($student->zkteco_synced_at)->format('M d, Y H:i') }}
                                </div>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('students.sync-device', $student->id) }}" class="pt-2">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center gap-2 w-full px-3.5 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-xs transition shadow-sm">
                                <flux:icon icon="arrow-path" class="size-3.5" />
                                <span>Sync to Hardware</span>
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Fee Ledger & Academic Quick Actions --}}
                <div class="bg-zinc-50 rounded-3xl p-5 border border-zinc-200/80 flex flex-col gap-2.5 text-xs">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block">Quick Navigation</span>
                    <flux:button href="{{ route('student.index') }}" variant="subtle" icon="arrow-left" class="justify-start">
                        Back to Student Directory
                    </flux:button>
                    @can('view fee')
                        <flux:button href="{{ route('feemanagement.index', ['search' => $student->name]) }}" variant="subtle" icon="banknotes" class="justify-start">
                            View Fee Transactions
                        </flux:button>
                    @endcan
                    @can('delete student')
                        <x-confirm-form
                            :action="route('student.destroy', $student->id)"
                            title="Remove {{ $student->name }} from the directory?"
                            message="This deletes the student's record and login. Recorded results are kept."
                            confirm="Remove Student"
                            label="Remove Student"
                            variant="subtle"
                            trigger-class="text-rose-600 hover:bg-rose-50 w-full justify-start"
                            class="w-full" />
                    @endcan
                </div>

            </div>

        </div>

    </div>
</x-layouts::app>