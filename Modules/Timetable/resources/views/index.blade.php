<x-layouts::app :title="__(config('timetable.name'))">
    <div class="flex flex-col gap-6 w-full" x-data="{ viewMode: 'grid' }">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-indigo-50 text-indigo-700 border border-indigo-200/60 dark:bg-indigo-950/50 dark:text-indigo-400 dark:border-indigo-800/60 flex items-center gap-1.5">
                        <span class="size-1.5 rounded-full bg-indigo-500"></span>
                        Academic Schedule
                    </span>
                    <span class="text-xs text-zinc-400">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">{{ currentInstitution()?->name ?? 'Green Valley Academy' }}</span>
                </div>

                <flux:heading size="xl" class="text-2xl font-black tracking-tight text-zinc-900 dark:text-white">
                    @if ($isTeacher)
                        My Academic Timetable
                    @elseif ($isStudent)
                        My Class Timetable
                    @elseif ($isParent)
                        My Child's Timetable
                    @else
                        Master Class Timetables
                    @endif
                </flux:heading>
                <flux:text class="text-zinc-500 text-xs sm:text-sm">
                    Manage weekly lesson distributions, period allocations, and venue locations.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 border border-zinc-200 dark:border-zinc-700 shadow-2xs hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors">
                    <flux:icon icon="printer" class="size-3.5 text-zinc-400" />
                    Print Schedule
                </button>

                @can('create timetable')
                    <flux:button href="{{ route('timetable.import') }}" icon="arrow-up-tray" variant="subtle" class="font-semibold text-xs">
                        Import CSV
                    </flux:button>
                    <flux:button href="{{ route('timetable.create') }}" icon="plus" variant="primary" class="font-semibold text-xs shadow-sm">
                        Add Entry
                    </flux:button>
                @endcan
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200/60 bg-emerald-50/60 dark:bg-emerald-950/40 dark:border-emerald-800/60 p-4 flex items-center gap-3 shadow-2xs">
                <flux:icon icon="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                <span class="text-xs font-medium text-emerald-800 dark:text-emerald-300">{{ session('success') }}</span>
            </div>
        @endif

        {{-- Analytical Metric Tiles --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-kpi-card 
                title="Weekly Load" 
                icon="clock" 
                color="indigo" 
                :count="$totalWeeklyPeriods . ' Periods'" 
                subtext="total weekly allocations" 
            />
            <x-kpi-card 
                title="Assigned Streams" 
                icon="rectangle-group" 
                color="sky" 
                :count="$uniqueStreamsCount . ' Classes'" 
                subtext="teaching class sections" 
            />
            <x-kpi-card 
                title="Core Subjects" 
                icon="book-open" 
                color="emerald" 
                :count="$uniqueSubjectsCount . ' Disciplines'" 
                subtext="curriculum offerings" 
            />
            <x-kpi-card 
                title="Today's Lessons" 
                icon="calendar-days" 
                color="amber" 
                :count="$todayPeriodsCount . ' Sessions'" 
                subtext="scheduled on {{ $currentDayName }}" 
            />
        </div>

        {{-- View Switcher & Picker Toolbar --}}
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-3 sm:p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
            {{-- Dual View Switcher Tabs --}}
            <div class="inline-flex p-1 bg-zinc-100 dark:bg-zinc-800 rounded-xl">
                <button 
                    type="button" 
                    @click="viewMode = 'grid'" 
                    :class="viewMode === 'grid' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white shadow-2xs font-bold' : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-white font-medium'"
                    class="px-3.5 py-1.5 rounded-lg text-xs transition-all inline-flex items-center gap-1.5"
                >
                    <flux:icon icon="table-cells" class="size-3.5" />
                    Weekly Grid
                </button>
                <button 
                    type="button" 
                    @click="viewMode = 'agenda'" 
                    :class="viewMode === 'agenda' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white shadow-2xs font-bold' : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-white font-medium'"
                    class="px-3.5 py-1.5 rounded-lg text-xs transition-all inline-flex items-center gap-1.5"
                >
                    <flux:icon icon="calendar-days" class="size-3.5" />
                    Daily Agenda
                </button>
            </div>

            {{-- Class Picker (when applicable) --}}
            @if ($showPicker)
                <form method="GET" action="{{ route('timetable.index') }}" class="flex items-center gap-2">
                    <label for="class_picker" class="text-xs font-semibold text-zinc-500 shrink-0">Class Roster:</label>
                    <select id="class_picker" name="class_id" onchange="this.form.submit()" class="text-xs font-medium bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden">
                        <option value="">Select a class...</option>
                        @foreach ($classes as $schoolClass)
                            <option value="{{ $schoolClass->id }}" {{ $selectedClass?->id === $schoolClass->id ? 'selected' : '' }}>
                                {{ $schoolClass->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        {{-- Schedule Display Card --}}
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-3xl overflow-hidden shadow-xs">
            
            @if ($isTeacher)
                @if ($periods->isEmpty())
                    <x-empty-state 
                        icon="calendar" 
                        title="No Schedule Assigned" 
                        description="You have no timetable entries assigned yet. Contact your school administrator."
                        color="indigo"
                    />
                @else
                    <div x-show="viewMode === 'grid'">
                        @include('timetable::partials._grid', ['grid' => $grid, 'days' => $days, 'periods' => $periods])
                    </div>
                    <div x-show="viewMode === 'agenda'">
                        @include('timetable::partials._agenda', ['entries' => $entries, 'days' => $days, 'currentDayName' => $currentDayName])
                    </div>
                @endif
                
            @elseif (($isStudent || $isParent) && $classes->isEmpty())
                <x-empty-state 
                    icon="academic-cap" 
                    title="No Class Assigned" 
                    description="{{ $isStudent ? 'You are not assigned to a class yet. Contact your school administrator.' : 'No child is assigned to a class yet. Contact your school administrator.' }}"
                    color="amber"
                />
                
            @elseif (! $isStudent && ! $isParent && $classes->isEmpty())
                <x-empty-state 
                    icon="rectangle-group" 
                    title="No Classes Available" 
                    description="There are no classes created in the system yet."
                    color="sky"
                />
                
            @elseif (!$selectedClass)
                <x-empty-state 
                    icon="cursor-arrow-rays" 
                    title="Select a Class" 
                    description="Choose a class from the dropdown above to view its timetable schedule."
                    color="indigo"
                />
                
            @else
                <div class="px-6 py-4 border-b border-zinc-200/80 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/20 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <flux:heading size="lg" class="font-bold">{{ $selectedClass->name }} Master Schedule</flux:heading>
                    @if ($selectedClass->classTeacher)
                        <div class="flex items-center gap-1.5 text-xs text-zinc-600 dark:text-zinc-400 bg-white dark:bg-zinc-800 px-3 py-1 rounded-full border border-zinc-200 dark:border-zinc-700">
                            <flux:icon icon="user" class="size-3.5 text-indigo-500" />
                            Class Teacher: <span class="font-bold text-zinc-900 dark:text-white">{{ $selectedClass->classTeacher->name }}</span>
                        </div>
                    @endif
                </div>

                @if ($periods->isEmpty())
                    <x-empty-state 
                        icon="calendar" 
                        title="No Schedule Yet" 
                        description="There are no timetable entries recorded for this class yet."
                        color="indigo"
                    />
                @else
                    <div x-show="viewMode === 'grid'">
                        @include('timetable::partials._grid', ['grid' => $grid, 'days' => $days, 'periods' => $periods])
                    </div>
                    <div x-show="viewMode === 'agenda'">
                        @include('timetable::partials._agenda', ['entries' => $entries, 'days' => $days, 'currentDayName' => $currentDayName])
                    </div>
                @endif
            @endif

        </div>
    </div>
</x-layouts::app>
