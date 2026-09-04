@php
    $teacherUser = $teacherDetails->teacher;
    $teacherName = $teacherUser?->name ?? 'Faculty Member';
    $teacherSubjects = teacherSubjects($teacherDetails->teacher_id);
    $teacherClasses = teacherClasses($teacherDetails->teacher_id);
    // Clean initials calculation
    $clean = trim(preg_replace('/^(mr\.|mrs\.|ms\.|dr\.|prof\.)\s+/i', '', $teacherName));
    $parts = array_filter(explode(' ', $clean));
    $initials =
        count($parts) >= 2
            ? mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1))
            : mb_strtoupper(mb_substr($clean ?: 'TC', 0, 2));

    $empNumber = $teacherDetails->employee_number ?? 'EMP-T-' . str_pad($teacherDetails->id, 3, '0', STR_PAD_LEFT);
    $status = $teacherDetails->status ?? 'active';
@endphp

<x-layouts::app :title="__('Faculty Profile - ' . $teacherName)">
    <div class="flex flex-col gap-8 w-full max-w-6xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Navigation & Breadcrumbs --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <a href="{{ route('teacher.index') }}" wire:navigate.hover
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-500 hover:text-zinc-900 transition">
                        <flux:icon icon="arrow-left" class="size-3.5" />
                        <span>Back to Teachers Directory</span>
                    </a>
                    <span class="text-xs text-zinc-300">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">Faculty Dossier</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">{{ $teacherName }}
                </flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Academic department credentials, contact specifications, and administrative record.
                </flux:text>
            </div>

            <div class="flex items-center gap-2">
                @can('edit teacher')
                    <flux:button href="{{ route('teacher.edit', $teacherDetails->teacher_id) }}" wire:navigate.hover
                        icon="pencil-square" variant="primary" class="font-semibold shadow-sm">
                        Edit Profile
                    </flux:button>
                @endcan
            </div>
        </div>

        {{-- Flash Messages --}}
        @if (session('success'))
            <div
                class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <flux:icon icon="check-circle" class="size-5 text-emerald-600 shrink-0" />
                    <span class="text-sm font-semibold text-emerald-900">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        {{-- Executive Identity Banner (Zero Overlap Guaranteed) --}}
        <div
            class="bg-white rounded-3xl p-6 sm:p-8 border border-zinc-200/80 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
            <div style="display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap;">
                <div style="width: 4rem; height: 4rem; min-width: 4rem; min-height: 4rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"
                    class="rounded-2xl bg-blue-50 border border-blue-200 text-blue-700 font-black text-xl shadow-xs">
                    {{ $initials }}
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                    <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
                        <h2 class="text-xl font-black text-zinc-900 tracking-tight">{{ $teacherName }}</h2>
                        <span
                            class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-zinc-100 text-zinc-800 border border-zinc-200">
                            {{ $teacherDetails->department ?? 'General Faculty' }}
                        </span>
                        <span
                            class="px-2.5 py-0.5 rounded-full text-xs font-bold
                            {{ $status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-zinc-100 text-zinc-600 border border-zinc-200' }}">
                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                        </span>
                    </div>

                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;"
                        class="text-xs text-zinc-500 font-mono">
                        <span
                            class="px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-800 font-bold border border-zinc-200/80">{{ $empNumber }}</span>
                        <span class="text-zinc-300">&bull;</span>
                        <span
                            class="font-sans font-semibold text-zinc-700">{{ $teacherDetails->institution?->name ?? 'Main Campus' }}</span>
                    </div>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                @if ($teacherDetails->phone)
                    <a href="tel:{{ $teacherDetails->phone }}"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-800 text-xs font-bold transition border border-zinc-200/60">
                        <flux:icon icon="phone" class="size-3.5 text-zinc-500" />
                        <span>Call Phone</span>
                    </a>
                @endif
                @if ($teacherUser?->email)
                    <a href="mailto:{{ $teacherUser->email }}"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-800 text-xs font-bold transition border border-zinc-200/60">
                        <flux:icon icon="envelope" class="size-3.5 text-zinc-500" />
                        <span>Send Email</span>
                    </a>
                @endif
            </div>
        </div>

        {{-- 2-Column Responsive Layout --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left Column (Broad: Academic & Employment Specifications) --}}
            <div class="lg:col-span-2 flex flex-col gap-6">

                {{-- Employment Record Card --}}
                <div class="bg-white rounded-3xl p-6 border border-zinc-200/80 shadow-sm space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b border-zinc-100">
                        <flux:icon icon="academic-cap" class="size-4 text-zinc-500" />
                        <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-700">Academic & Employment
                            Record</h3>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div class="p-4 rounded-2xl bg-zinc-50 border border-zinc-100">
                            <span
                                class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Primary
                                Department</span>
                            <span
                                class="font-bold text-zinc-900 text-sm">{{ $teacherDetails->department ?? 'General Academic' }}</span>
                        </div>

                        <div class="p-4 rounded-2xl bg-zinc-50 border border-zinc-100">
                            <span
                                class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Highest
                                Qualification</span>
                            <span
                                class="font-bold text-zinc-900 text-sm">{{ $teacherDetails->qualification ?? 'B.Ed' }}</span>
                        </div>

                        <div class="p-4 rounded-2xl bg-zinc-50 border border-zinc-100">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Hire
                                Date</span>
                            <span class="font-mono font-bold text-zinc-900 text-sm">
                                {{ $teacherDetails->hire_date ? $teacherDetails->hire_date->format('M d, Y') : '—' }}
                            </span>
                        </div>

                        <div class="p-4 rounded-2xl bg-zinc-50 border border-zinc-100">
                            <span
                                class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Assigned
                                Campus</span>
                            <span
                                class="font-bold text-zinc-900 text-sm">{{ $teacherDetails->institution?->name ?? 'Main Campus' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Address & Notes Card --}}
                <div class="bg-white rounded-3xl p-6 border border-zinc-200/80 shadow-sm space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b border-zinc-100">
                        <flux:icon icon="document-text" class="size-4 text-zinc-500" />
                        <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-700">Administrative Notes &
                            Address</h3>
                    </div>

                    <div class="space-y-4 text-xs">
                        <div>
                            <span
                                class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Residential
                                Address</span>
                            <p class="text-zinc-800 font-medium bg-zinc-50 p-4 rounded-2xl border border-zinc-100">
                                {{ $teacherDetails->address ?: 'No residential address recorded.' }}
                            </p>
                        </div>

                        <div>
                            <span
                                class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Administrative
                                Notes</span>
                            <p
                                class="text-zinc-800 font-medium bg-zinc-50 p-4 rounded-2xl border border-zinc-100 whitespace-pre-line">
                                {{ $teacherDetails->notes ?: 'No administrative remarks logged for this faculty member.' }}
                            </p>
                        </div>
                    </div>
                </div>
                {{-- Teacher Subjects Card --}}
                <flux:card
                    class="md:col-span-2 bg-sky-50/50 dark:bg-sky-900/10 border border-sky-200/50 dark:border-sky-800/30 rounded-xl p-4 flex flex-col gap-3">
                    <div class="flex items-center gap-2 pb-3 border-b border-zinc-100">
                        <flux:icon icon="book-open" class="size-4 text-zinc-500" />
                        <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-700">Subjects</h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
                        @forelse ($teacherSubjects as $subject)
                            <flux:text
                                class="bg-white dark:bg-zinc-800/30 border border-zinc-200/50 dark:border-zinc-700/30 rounded-lg p-3 flex items-center gap-3">
                                <flux:icon icon="book-open" class="size-4 text-zinc-500 dark:text-zinc-300" />
                                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ getSubjectName($subject->subject_id) }}
                                </span>
                            </flux:text>
                        @empty
                            <flux:text class="text-sm text-zinc-500">No subjects assigned.</flux:text>
                        @endforelse
                    </div>
                </flux:card>
                {{-- Teacher Classes Card --}}
                <flux:card
                    class="md:col-span-2 bg-emerald-50/50 dark:bg-emerald-900/10 border border-emerald-200/50 dark:border-emerald-800/30 rounded-xl p-4 flex flex-col gap-3">
                    <div class="flex items-center gap-2 pb-3 border-b border-zinc-100">
                        <flux:icon icon="academic-cap" class="size-4 text-zinc-500" />
                        <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-700">Classes</h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
                        @forelse ($teacherClasses as $teacherClass)
                            <flux:text
                                class="bg-white dark:bg-zinc-800/30 border border-zinc-200/50 dark:border-zinc-700/30 rounded-lg p-3 flex items-center gap-3">
                                <flux:icon icon="academic-cap" class="size-4 text-zinc-500 dark:text-zinc-300" />
                                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ getClassName($teacherClass->class_id) }}
                                </span>
                            </flux:text>
                        @empty
                            <flux:text class="text-sm text-zinc-500">No classes assigned.</flux:text>
                        @endforelse
                    </div>
                </flux:card>
            </div>

            {{-- Right Column (Narrow: Account Specifications & Actions) --}}
            <div class="flex flex-col gap-6">

                {{-- Account Specifications Card --}}
                <div class="bg-white rounded-3xl p-6 border border-zinc-200/80 shadow-sm space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b border-zinc-100">
                        <flux:icon icon="user-circle" class="size-4 text-zinc-500" />
                        <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-700">System Account</h3>
                    </div>

                    <div class="space-y-3.5 text-xs">
                        <div>
                            <span
                                class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Email
                                Address</span>
                            <span class="font-medium text-zinc-900 text-xs">{{ $teacherUser?->email ?? '—' }}</span>
                        </div>

                        <div>
                            <span
                                class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">System
                                Role</span>
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700 font-bold text-xs border border-blue-200/60">
                                Teacher / Faculty
                            </span>
                        </div>

                        <div>
                            <span
                                class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Account
                                Access</span>
                            <span
                                class="font-bold {{ $teacherDetails->is_active ? 'text-emerald-700' : 'text-zinc-500' }}">
                                {{ $teacherDetails->is_active ? 'Active & Enabled' : 'Disabled' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Quick Actions Card --}}
                <div class="bg-zinc-50 rounded-3xl p-5 border border-zinc-200/80 flex flex-col gap-2.5 text-xs">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block">Faculty
                        Actions</span>
                    <flux:button href="{{ route('teacher.index') }}" variant="subtle" icon="arrow-left"
                        class="justify-start">
                        Back to Directory
                    </flux:button>
                    @can('edit teacher')
                        <flux:button href="{{ route('teacher.edit', $teacherDetails->teacher_id) }}" variant="primary"
                            icon="pencil-square" class="justify-start">
                            Edit Teacher Details
                        </flux:button>
                    @endcan
                    @can('delete teacher')
                        <form action="{{ route('teacher.destroy', $teacherDetails->teacher_id) }}" method="POST"
                            onsubmit="return confirm('Permanently remove {{ addslashes($teacherName) }} from faculty roster?');"
                            class="w-full">
                            @csrf
                            @method('DELETE')
                            <flux:button type="submit" variant="subtle"
                                class="text-rose-600 hover:bg-rose-50 w-full justify-start" icon="trash">
                                Remove Teacher
                            </flux:button>
                        </form>
                    @endcan
                </div>

            </div>

        </div>

    </div>
</x-layouts::app>
