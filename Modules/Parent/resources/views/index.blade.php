@php
    $totalParentsCount = method_exists($parents, 'total') ? $parents->total() : count($parents);
    $totalLinkedChildren = $parents->sum(fn($p) => $p->children->count());
    $withPhoneCount = $parents->filter(fn($p) => !empty($p->parent?->parent_phone))->count();
    $contactReachPct = count($parents) > 0 ? round(($withPhoneCount / count($parents)) * 100, 1) : 100;
@endphp

<x-layouts::app :title="__('Parents & Guardians')">
    <div class="flex flex-col gap-8 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8" x-data="{
        search: '',
        filter: 'all',
        matches(p) {
            const matchesSearch = !this.search || 
                p.name.toLowerCase().includes(this.search.toLowerCase()) || 
                p.email.toLowerCase().includes(this.search.toLowerCase()) ||
                p.phone.toLowerCase().includes(this.search.toLowerCase()) ||
                p.occupation.toLowerCase().includes(this.search.toLowerCase()) ||
                p.students.toLowerCase().includes(this.search.toLowerCase());
            
            const matchesFilter = this.filter === 'all' || 
                (this.filter === 'with_students' && p.has_students) ||
                (this.filter === 'multi_child' && p.children_count > 1) ||
                (this.filter === 'no_students' && !p.has_students);

            return matchesSearch && matchesFilter;
        }
    }">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-blue-50 text-blue-700 border border-blue-200/60">
                        Guardian Directory
                    </span>
                    <span class="text-xs text-zinc-400">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">{{ $institution?->name ?? currentInstitution()?->name ?? 'Green Valley Academy' }}</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Parents & Guardians</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Manage parent profiles, emergency contacts, and linked student relationships.
                </flux:text>
            </div>
            
            @can('create parent')
                <div class="flex items-center gap-3">
                    <flux:button href="{{ route('parent.create') }}" icon="plus" variant="primary" class="font-semibold shadow-sm px-4">
                        Add Parent
                    </flux:button>
                </div>
            @endcan
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

        {{-- Top Family Overview KPI Strip --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <x-kpi-card 
                title="Registered Guardians" 
                icon="users" 
                color="sky" 
                :count="$totalParentsCount" 
                subtext="Verified guardian accounts" 
            />
            
            <x-kpi-card 
                title="Enrolled Learners" 
                icon="academic-cap" 
                color="indigo" 
                :count="$totalLinkedChildren" 
                subtext="Direct guardian links" 
            />
            
            <x-kpi-card 
                title="Communication Reach" 
                icon="phone" 
                color="emerald" 
                :count="$contactReachPct" 
                countSuffix="%" 
                subtext="Verified mobile lines for SMS & Email" 
            />
        </div>

        {{-- Filter & Search Toolbar --}}
        <div class="bg-white rounded-2xl p-4 border border-zinc-200/80 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
            {{-- Search Bar Form --}}
            <form action="{{ route('parent.index') }}" method="GET" class="relative w-full md:w-96 flex items-center gap-2">
                @if(request('filter'))
                    <input type="hidden" name="filter" value="{{ request('filter') }}">
                @endif
                <div class="relative w-full">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400">
                        <flux:icon icon="magnifying-glass" class="size-4" />
                    </div>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by child ADM, student name, parent phone..." 
                        style="width: 100%; padding-left: 2.75rem !important; padding-right: 1rem; padding-top: 0.5rem; padding-bottom: 0.5rem;" class="bg-zinc-50 border border-zinc-200 rounded-xl text-xs placeholder-zinc-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                </div>
                <button type="submit" class="px-4 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-bold transition shrink-0">
                    Search
                </button>
                @if(request('search'))
                    <a href="{{ route('parent.index', ['filter' => request('filter')]) }}" class="px-2.5 py-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-600 text-xs font-semibold transition shrink-0" title="Clear Search">
                        ✕
                    </a>
                @endif
            </form>

            {{-- Filter Chips --}}
            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                <a href="{{ route('parent.index', ['search' => request('search')]) }}"
                    class="px-3.5 py-1.5 rounded-xl text-xs transition duration-150 font-semibold {{ !request('filter') ? 'bg-zinc-900 text-white shadow-sm' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200' }}">
                    All Guardians
                </a>
                <a href="{{ route('parent.index', ['filter' => 'with_students', 'search' => request('search')]) }}"
                    class="px-3.5 py-1.5 rounded-xl text-xs transition duration-150 font-semibold {{ request('filter') === 'with_students' ? 'bg-zinc-900 text-white shadow-sm' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200' }}">
                    Enrolled
                </a>
                <a href="{{ route('parent.index', ['filter' => 'multi_child', 'search' => request('search')]) }}"
                    class="px-3.5 py-1.5 rounded-xl text-xs transition duration-150 font-semibold {{ request('filter') === 'multi_child' ? 'bg-zinc-900 text-white shadow-sm' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200' }}">
                    Multi-Child
                </a>
            </div>
        </div>

        {{-- Parents Ledger Table --}}
        <div class="bg-white shadow-sm border border-zinc-200/80 rounded-2xl overflow-hidden flex flex-col">
            @if($parents->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 text-zinc-500 border-b border-zinc-200/80 text-xs font-bold uppercase tracking-wider">
                            <tr>
                                <th class="py-3.5 px-5">Parent / Guardian</th>
                                <th class="py-3.5 px-5">Contact & Occupation</th>
                                <th class="py-3.5 px-5">Linked Students</th>
                                <th class="py-3.5 px-5">Status</th>
                                <th class="py-3.5 px-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach($parents as $parent)
                                @php
                                    $studentNames = $parent->children->map(fn($c) => ($c->student?->name ?? '') . ' ' . ($c->admission_number ?? '') . ' ' . ($c->student_number ?? '') . ' ' . $c->student_id)->filter()->implode(' ');
                                    $hasStudents = $parent->children->isNotEmpty();
                                    $initials = strtoupper(substr($parent->name, 0, 2));
                                    $phone = $parent->parent?->parent_phone ?? '';
                                    $occupation = $parent->parent?->parent_occupation ?? 'Guardian';
                                @endphp
                                <tr class="hover:bg-zinc-50/60 transition-colors"
                                    x-show="matches({
                                        name: '{{ addslashes($parent->name) }}',
                                        email: '{{ addslashes($parent->email) }}',
                                        phone: '{{ addslashes($phone) }}',
                                        occupation: '{{ addslashes($occupation) }}',
                                        students: '{{ addslashes($studentNames) }}',
                                        has_students: {{ $hasStudents ? 'true' : 'false' }},
                                        children_count: {{ $parent->children->count() }}
                                    })">
                                    
                                    {{-- Parent Column --}}
                                    <td class="py-4 px-5">
                                        <div class="flex items-center gap-3">
                                            <div class="size-10 rounded-xl bg-zinc-100 border border-zinc-200 font-bold text-xs flex items-center justify-center text-zinc-800 shrink-0">
                                                {{ $initials }}
                                            </div>
                                            <div>
                                                <a href="{{ route('parent.show', $parent->id) }}" class="font-bold text-zinc-900 hover:text-zinc-600 transition">
                                                    {{ $parent->name }}
                                                </a>
                                                <div class="text-xs text-zinc-400 mt-0.5 font-medium">
                                                    <a href="mailto:{{ $parent->email }}" class="hover:text-zinc-600 transition">{{ $parent->email }}</a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Contact & Occupation Column --}}
                                    <td class="py-4 px-5">
                                        @if($phone)
                                            <a href="tel:{{ $phone }}" class="text-xs font-semibold text-zinc-800 hover:text-blue-600 transition flex items-center gap-1.5">
                                                <flux:icon icon="phone" class="size-3 text-zinc-400 shrink-0" />
                                                <span>{{ $phone }}</span>
                                            </a>
                                        @else
                                            <span class="text-xs text-zinc-400 italic">No phone recorded</span>
                                        @endif
                                        <div class="text-[11px] text-zinc-500 mt-1 flex items-center gap-1 font-medium">
                                            <span class="px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-600 font-medium">
                                                {{ $occupation }}
                                            </span>
                                        </div>
                                    </td>

                                    {{-- Linked Students Column --}}
                                    <td class="py-4 px-5">
                                        @if($parent->children->isNotEmpty())
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach($parent->children as $child)
                                                    @if($child->student)
                                                        <a href="{{ route('student.show', $child->student->id) }}" 
                                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 text-xs font-semibold transition border border-zinc-200/60"
                                                            title="View Student File">
                                                            <span>{{ $child->student->name }}</span>
                                                            <flux:icon icon="arrow-up-right" class="size-3 text-zinc-400" />
                                                        </a>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-xs text-zinc-400 italic">No linked students</span>
                                        @endif
                                    </td>

                                    {{-- Status Column --}}
                                    <td class="py-4 px-5">
                                        @if($hasStudents)
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-zinc-100 text-zinc-600 border border-zinc-200">
                                                Inactive
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Actions Column --}}
                                    <td class="py-4 px-5 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <flux:button href="{{ route('parent.show', $parent->id) }}" size="sm" icon="eye" variant="subtle" tooltip="View Profile" />
                                            @can('edit parent')
                                                <flux:button href="{{ route('parent.edit', $parent->id) }}" size="sm" icon="pencil-square" variant="subtle" tooltip="Edit Parent" />
                                            @endcan
                                            @can('delete parent')
                                                <form action="{{ route('parent.destroy', $parent->id) }}" method="POST" class="inline" onsubmit="return confirm('Remove this guardian profile?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <flux:button type="submit" size="sm" icon="trash" variant="subtle" class="text-rose-500 hover:text-rose-600 hover:bg-rose-50" tooltip="Delete" />
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Table Footer Summary & Pagination --}}
                <div class="border-t border-zinc-200/80 px-6 py-4 bg-zinc-50/50 flex flex-col sm:flex-row justify-between items-center text-xs text-zinc-500 gap-4">
                    <div class="flex items-center gap-3">
                        <span>Showing <strong class="text-zinc-800 font-semibold">{{ $parents->count() }}</strong> of <strong class="text-zinc-800 font-semibold">{{ number_format($totalParentsCount) }}</strong> parents</span>
                        <span class="text-zinc-300">&bull;</span>
                        <span>Page <strong class="text-zinc-800 font-semibold">{{ $parents->currentPage() }}</strong> of <strong class="text-zinc-800 font-semibold">{{ $parents->lastPage() }}</strong></span>
                    </div>
                    @if(method_exists($parents, 'links') && $parents->hasPages())
                        <div>
                            {{ $parents->links() }}
                        </div>
                    @endif
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="size-14 rounded-2xl bg-zinc-100 flex items-center justify-center text-zinc-400 mb-3">
                        <flux:icon icon="users" class="size-7" />
                    </div>
                    <h4 class="text-base font-bold text-zinc-900">No Parents Registered Yet</h4>
                    <p class="text-zinc-500 text-xs max-w-sm mx-auto mt-1 mb-4">
                        Get started by registering parent and guardian contact profiles for your institution.
                    </p>
                    @can('create parent')
                        <flux:button href="{{ route('parent.create') }}" variant="primary" icon="plus" size="sm">
                            Add First Parent
                        </flux:button>
                    @endcan
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>