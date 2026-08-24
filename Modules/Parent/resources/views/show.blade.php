@php
    $initials = $parent->initials();
    $phone = $parent->parent?->parent_phone ?? '';
    $occupation = $parent->parent?->parent_occupation ?? 'Guardian';
    $linkedCount = $parent->children->count();
@endphp

<x-layouts::app :title="__('Guardian Profile')">
    <div class="flex flex-col gap-8 w-full max-w-5xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Navigation & Breadcrumbs --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <a href="{{ route('parent.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-500 hover:text-zinc-900 transition">
                        <flux:icon icon="arrow-left" class="size-3.5" />
                        <span>Back to Parents Directory</span>
                    </a>
                    <span class="text-xs text-zinc-300">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">Guardian File</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">{{ $parent->name }}</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Guardian overview, direct contact details, and linked student academic files.
                </flux:text>
            </div>

            <div class="flex items-center gap-2">
                @can('edit parent')
                    <flux:button href="{{ route('parent.edit', $parent->id) }}" icon="pencil-square" variant="primary" class="font-semibold shadow-sm">
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

        {{-- Guardian Profile Header Card --}}
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-zinc-200/80 shadow-sm relative overflow-hidden">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-6">
                <div class="flex items-start gap-4">
                    <div class="size-16 rounded-2xl bg-zinc-100 border border-zinc-200 text-zinc-800 font-black text-xl flex items-center justify-center shadow-sm shrink-0">
                        {{ $initials }}
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h3 class="text-xl font-black text-zinc-900">{{ $parent->name }}</h3>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                Active Guardian
                            </span>
                        </div>
                        
                        <div class="flex flex-wrap items-center gap-4 mt-2 text-xs text-zinc-500 font-medium">
                            <a href="mailto:{{ $parent->email }}" class="flex items-center gap-1.5 text-zinc-600 hover:text-blue-600 transition">
                                <flux:icon icon="envelope" class="size-3.5 text-zinc-400" />
                                <span>{{ $parent->email }}</span>
                            </a>
                            @if($phone)
                                <a href="tel:{{ $phone }}" class="flex items-center gap-1.5 text-zinc-600 hover:text-blue-600 transition">
                                    <flux:icon icon="phone" class="size-3.5 text-zinc-400" />
                                    <span>{{ $phone }}</span>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Direct Quick Communication Action --}}
                <div class="flex items-center gap-2">
                    @if($phone)
                        <a href="tel:{{ $phone }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-800 text-xs font-bold transition">
                            <flux:icon icon="phone" class="size-3.5" />
                            <span>Call Phone</span>
                        </a>
                    @endif
                    <a href="mailto:{{ $parent->email }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-bold transition shadow-sm">
                        <flux:icon icon="envelope" class="size-3.5" />
                        <span>Send Email</span>
                    </a>
                </div>
            </div>

            {{-- Demographic Summary Badges --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6 pt-6 border-t border-zinc-100">
                <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-100">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Occupation</span>
                    <span class="text-sm font-black text-zinc-900">{{ $occupation }}</span>
                </div>
                <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-100">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Linked Learners</span>
                    <span class="text-sm font-black text-zinc-900">{{ $linkedCount }} Enrolled {{ Str::plural('Child', $linkedCount) }}</span>
                </div>
                <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-100">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Campus Affiliation</span>
                    <span class="text-sm font-black text-zinc-900">{{ currentInstitution()?->name ?? 'Green Valley Academy' }}</span>
                </div>
            </div>
        </div>

        {{-- Linked Children Section --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-zinc-900">Enrolled Children & Students</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">Students officially under the legal custody and guardianship of {{ $parent->name }}.</p>
                </div>
                <span class="px-2.5 py-1 rounded-lg bg-zinc-100 text-zinc-700 text-xs font-semibold font-mono">
                    {{ $linkedCount }} {{ Str::plural('Student', $linkedCount) }}
                </span>
            </div>

            @if ($parent->children->isEmpty())
                <div class="bg-white rounded-3xl border border-zinc-200 p-12 text-center shadow-sm">
                    <flux:icon icon="academic-cap" class="size-12 mx-auto text-zinc-300 mb-3" />
                    <h4 class="text-base font-bold text-zinc-900">No Linked Students</h4>
                    <p class="text-xs text-zinc-500 max-w-sm mx-auto mt-1 mb-4">
                        There are currently no active students linked to this parent profile.
                    </p>
                    @can('edit parent')
                        <flux:button href="{{ route('parent.edit', $parent->id) }}" variant="primary" size="sm">
                            Link Students
                        </flux:button>
                    @endcan
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach ($parent->children as $child)
                        @php
                            $studentUser = $child->student;
                            $studentInitials = $studentUser ? strtoupper(substr($studentUser->name, 0, 2)) : 'ST';
                            $className = $child->schoolClass?->name ?? (\Modules\Classes\Models\SchoolClass::find($child->class_id)?->name ?? 'Primary Stream');
                        @endphp
                        <div class="bg-white rounded-2xl p-5 border border-zinc-200/80 shadow-sm hover:border-zinc-300 transition duration-150 flex flex-col justify-between">
                            <div>
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="flex items-center gap-3">
                                        <div class="size-11 rounded-xl bg-zinc-100 border border-zinc-200 font-bold text-xs flex items-center justify-center text-zinc-800 shrink-0">
                                            {{ $studentInitials }}
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-zinc-900">
                                                {{ $studentUser?->name ?? 'Student Name' }}
                                            </h4>
                                            <div class="text-xs text-zinc-500 font-mono mt-0.5">
                                                {{ $child->admission_number ?? 'ADM-—' }}
                                            </div>
                                        </div>
                                    </div>

                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        {{ ucfirst($child->enrollment_status ?? 'Active') }}
                                    </span>
                                </div>

                                <div class="space-y-1.5 text-xs text-zinc-500 py-2 border-t border-zinc-100">
                                    <div class="flex items-center justify-between">
                                        <span class="text-zinc-400">Class & Stream:</span>
                                        <span class="font-semibold text-zinc-800">{{ $className }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-zinc-400">Institution:</span>
                                        <span class="font-medium text-zinc-700">{{ $child->institution?->name ?? 'Green Valley' }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-3 mt-1 border-t border-zinc-100 flex items-center justify-between gap-2">
                                @if($studentUser)
                                    <a href="{{ route('student.show', $studentUser->id) }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-900 hover:text-blue-600 transition">
                                        <span>View Student File</span>
                                        <flux:icon icon="arrow-right" class="size-3" />
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Footer Action Bar --}}
        <div class="bg-zinc-50 rounded-2xl p-4 border border-zinc-200/80 flex items-center justify-between">
            <flux:button href="{{ route('parent.index') }}" variant="subtle" icon="arrow-left">
                Back to Directory
            </flux:button>

            <div class="flex items-center gap-2">
                @can('edit parent')
                    <flux:button href="{{ route('parent.edit', $parent->id) }}" icon="pencil-square" variant="primary">
                        Edit Parent
                    </flux:button>
                @endcan

                @can('delete parent')
                    <form action="{{ route('parent.destroy', $parent->id) }}" method="POST"
                        onsubmit="return confirm('Remove this parent? Linked students will be unlinked, not deleted.');">
                        @csrf
                        @method('DELETE')
                        <flux:button type="submit" variant="subtle" class="text-rose-600 hover:bg-rose-50" icon="trash">
                            Delete
                        </flux:button>
                    </form>
                @endcan
            </div>
        </div>

    </div>
</x-layouts::app>