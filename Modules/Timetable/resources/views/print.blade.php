<!DOCTYPE html>
<html lang="en" class="h-full bg-zinc-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — Notice Board Schedule</title>

    <link rel="icon" href="{{ asset('logos/solforbs-logo.png') }}" type="image/png">
    @fonts
    @vite(['resources/css/app.css'])

    <style>
        @page {
            size: landscape;
            margin: 6mm 8mm;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                color: #0f172a !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .print-card {
                border: 1px solid #94a3b8 !important;
                box-shadow: none !important;
                border-radius: 8px !important;
            }
            .print-table {
                border-collapse: collapse !important;
                width: 100% !important;
            }
            .print-table th, .print-table td {
                border: 1px solid #cbd5e1 !important;
            }
            .notice-board-container {
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }
        }

        /* Screen Preview Styling */
        .print-table {
            border-collapse: collapse;
        }
    </style>
</head>
<body class="min-h-screen text-zinc-900 bg-zinc-100 dark:bg-zinc-950 font-sans antialiased selection:bg-indigo-500 selection:text-white">

    {{-- Screen Only Action Bar --}}
    <header class="no-print sticky top-0 z-40 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md border-b border-zinc-200 dark:border-zinc-800 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('timetable.index', ['class_id' => $selectedClass?->id]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 transition">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Studio
                </a>

                @if (! $isTeacher && $classes->count() > 1)
                    <div class="flex items-center gap-2">
                        <label for="classSelector" class="text-xs font-bold text-zinc-500">Switch Class:</label>
                        <select id="classSelector" onchange="window.location.href = '{{ route('timetable.print') }}?class_id=' + this.value" class="text-xs font-semibold bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg px-2.5 py-1 text-zinc-900 dark:text-zinc-100">
                            @foreach ($classes as $c)
                                <option value="{{ $c->id }}" {{ $selectedClass?->id === $c->id ? 'selected' : '' }}>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <span class="text-xs text-zinc-500 hidden md:inline-flex items-center gap-1">
                    <svg class="size-3.5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Notice Board Layout &bull; Landscape (A4)
                </span>

                <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm transition cursor-pointer">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print Timetable
                </button>
            </div>
        </div>
    </header>

    {{-- Main Notice Board Container --}}
    <main class="notice-board-container max-w-[1400px] mx-auto p-4 sm:p-6 lg:p-8">
        <div class="print-card bg-white rounded-3xl border border-zinc-200 dark:border-zinc-800 p-6 sm:p-8 shadow-sm space-y-6">

            {{-- 1. Official School Letterhead / Noticeboard Header --}}
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pb-4 border-b-2 border-[#0047FF]">
                <div class="flex items-center gap-4 text-center sm:text-left">
                    @if (! empty($logoDataUri))
                        <img src="{{ $logoDataUri }}" alt="{{ $institution?->name ?? 'School Logo' }}" class="size-16 object-contain rounded-xl shrink-0">
                    @elseif ($institution?->logo)
                        <img src="{{ Storage::url($institution->logo) }}" alt="{{ $institution->name }}" translate="no" class="size-16 object-contain rounded-xl shrink-0">
                    @else
                        <img src="{{ asset('logos/solforbs-logo.png') }}" alt="{{ config('app.name', 'Solforbs') }}" class="size-16 object-contain rounded-xl shrink-0">
                    @endif

                    <div>
                        <h1 class="text-xl sm:text-2xl font-black uppercase tracking-tight text-zinc-900 dark:text-zinc-100">
                            {{ $institution?->name ?? config('app.name', 'School Management System') }}
                        </h1>
                        <p class="text-xs text-zinc-500 font-medium">
                            @if ($institution?->address) {{ $institution->address }} &bull; @endif
                            @if ($institution?->phone_number) Tel: {{ $institution->phone_number }} &bull; @endif
                            Official School Academic Schedule
                        </p>
                    </div>
                </div>

                {{-- Notice Board Badge Box --}}
                <div class="text-center sm:text-right">
                    <span class="inline-block px-3 py-1 rounded-md bg-[#0047FF] text-white text-[11px] font-black uppercase tracking-widest shadow-xs">
                        NOTICE BOARD COPY
                    </span>
                    <div class="text-xs font-bold text-[#0047FF] dark:text-[#00A3FF] mt-1">
                        {{ $academicYear }} &bull; {{ $term }}
                    </div>
                    <div class="text-[10px] text-zinc-400 font-mono">
                        Effective: {{ now()->format('d M Y') }}
                    </div>
                </div>
            </div>

            {{-- 2. Title & Class Meta Bar --}}
            <div class="flex flex-wrap items-center justify-between gap-3 bg-zinc-50 dark:bg-zinc-800/40 p-3 rounded-2xl border border-zinc-200/80 dark:border-zinc-700/80 border-l-4 border-l-[#0047FF]">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-sm sm:text-base font-black text-zinc-900 dark:text-white uppercase tracking-wide">
                        @if ($isTeacher)
                            Teacher: <span class="text-[#0047FF] dark:text-[#00A3FF]">{{ $teacher->name }}</span>
                        @else
                            Class: <span class="text-[#0047FF] dark:text-[#00A3FF]">{{ $selectedClass?->name }}</span>
                        @endif
                    </span>

                    @if ($selectedClass?->classTeacher && ! $isTeacher)
                        <span class="text-xs text-zinc-600 dark:text-zinc-400 font-semibold px-2.5 py-0.5 rounded-full bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700">
                            Class Teacher: {{ $selectedClass->classTeacher->name }}
                        </span>
                    @endif

                    @if ($selectedClass?->curriculum)
                        <span class="text-xs text-indigo-700 dark:text-indigo-300 font-bold px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950 border border-indigo-200 dark:border-indigo-800">
                            {{ $selectedClass->curriculum->systemLabel() }}
                        </span>
                    @endif
                </div>

                <div class="text-[11px] font-semibold text-zinc-500">
                    Teaching Routine: <strong>{{ count(collect($periods)->filter(fn($p) => empty($p['is_break']))) }} Lessons / Day</strong>
                </div>
            </div>

            {{-- 3. Visual Timetable Grid (Matching Main UI) --}}
            <div class="w-full overflow-hidden rounded-2xl border border-zinc-200/90 dark:border-zinc-800 shadow-2xs">
                <table class="print-table w-full table-fixed text-left border-collapse">
                    <thead>
                        <tr class="bg-zinc-100/80 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700">
                            <th class="p-2 sm:p-2.5 text-xs font-black uppercase tracking-wider text-zinc-600 dark:text-zinc-300 w-20 sm:w-24 md:w-28 border-r border-zinc-200 dark:border-zinc-700">
                                Day / Time
                            </th>
                            @foreach ($periods as $period)
                                @php
                                    $isBreakSlot = is_array($period) && !empty($period['is_break']);
                                    $slotName = is_array($period) ? ($period['name'] ?? $period['time']) : $period;
                                    $slotTime = is_array($period) ? $period['time'] : $period;
                                @endphp
                                <th class="p-1 sm:p-1.5 text-center text-xs font-bold border-r border-zinc-200 dark:border-zinc-700 last:border-r-0 {{ $isBreakSlot ? 'w-11 sm:w-13 md:w-16 bg-zinc-200/50 dark:bg-zinc-800/40' : 'text-zinc-900 dark:text-zinc-100' }}">
                                    <div class="text-[10px] sm:text-[11px] font-black uppercase tracking-tight truncate">
                                        {{ $slotName }}
                                    </div>
                                    <div class="text-[8px] sm:text-[9px] font-mono text-zinc-500 font-semibold mt-0.5 whitespace-nowrap">
                                        {{ $slotTime }}
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                        @foreach ($days as $day)
                            <tr class="border-b border-zinc-200 dark:border-zinc-800 last:border-b-0">
                                {{-- Day Header Cell --}}
                                <td class="p-2 sm:p-2.5 align-top font-black text-xs text-zinc-900 dark:text-white bg-zinc-50 dark:bg-zinc-800/40 border-r border-zinc-200 dark:border-zinc-700">
                                    <div class="text-[11px] sm:text-xs font-black text-zinc-900 dark:text-white">
                                        {{ $day }}
                                    </div>
                                    <div class="text-[9px] text-zinc-400 font-medium mt-0.5">
                                        @php
                                            $dayCount = collect($periods)->filter(fn($p) => empty($p['is_break']) && !empty($grid[$day][$p['time'] ?? $p]))->count();
                                        @endphp
                                        {{ $dayCount }} {{ Str::plural('lesson', $dayCount) }}
                                    </div>
                                </td>

                                {{-- Period Slots --}}
                                @foreach ($periods as $period)
                                    @php
                                        $isBreakSlot = is_array($period) && !empty($period['is_break']);
                                        $periodStr = is_array($period) ? $period['time'] : $period;
                                        $periodNumber = is_array($period) ? ($period['period_number'] ?? null) : null;
                                        $breakName = is_array($period) ? ($period['name'] ?? 'Break') : 'Break';
                                        $entry = $isBreakSlot ? null : ($grid[$day][$periodStr] ?? ($periodNumber ? ($grid[$day]['p_' . $periodNumber] ?? null) : null));
                                    @endphp

                                    <td class="p-1 sm:p-1.5 align-top border-r border-zinc-200 dark:border-zinc-700 last:border-r-0 {{ $isBreakSlot ? 'bg-zinc-100/60 dark:bg-zinc-800/30' : '' }}">
                                        @if ($isBreakSlot)
                                            {{-- Clean Break Cell --}}
                                            <div class="h-full min-h-[58px] rounded-lg flex flex-col items-center justify-center p-1 text-center select-none bg-zinc-100/80 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700">
                                                <span class="text-[9px] font-bold uppercase tracking-tight text-zinc-700 dark:text-zinc-300 truncate max-w-full">
                                                    {{ $breakName }}
                                                </span>
                                                <span class="text-[8px] font-mono text-zinc-400 mt-0.5 whitespace-nowrap">
                                                    {{ $periodStr }}
                                                </span>
                                            </div>
                                        @elseif ($entry)
                                            @php
                                                $isDouble = method_exists($entry, 'isDoubleLesson') && $entry->isDoubleLesson();
                                                $isPart1 = method_exists($entry, 'isDoubleLessonPart1') && $entry->isDoubleLessonPart1();
                                                $isPart2 = method_exists($entry, 'isDoubleLessonPart2') && $entry->isDoubleLessonPart2();
                                            @endphp

                                            {{-- Notice Board Lesson Card matching Web UI --}}
                                            <div class="relative p-1.5 rounded-lg border-l-3 transition-all text-left {{ $isDouble ? 'border-l-violet-600 bg-violet-50/30 dark:bg-violet-950/20 border border-zinc-200 dark:border-zinc-700' : 'border-l-indigo-600 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700' }} shadow-2xs">
                                                <div class="font-black text-[11px] sm:text-xs text-zinc-900 dark:text-white leading-tight truncate" title="{{ $entry->subject }}">
                                                    {{ $entry->subject }}
                                                </div>

                                                @if ($isDouble)
                                                    <div class="mt-0.5">
                                                        <span class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded text-[8px] font-extrabold bg-violet-100 dark:bg-violet-950 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-800/60 truncate max-w-full">
                                                            {{ $isPart2 ? 'Double (2)' : '2x Double' }}
                                                        </span>
                                                    </div>
                                                @endif

                                                <div class="flex items-center justify-between gap-1 mt-1 pt-1 border-t border-zinc-100 dark:border-zinc-800 text-[9px]">
                                                    <span class="font-medium text-zinc-600 dark:text-zinc-400 truncate flex-1" title="{{ $entry->teacher?->name ?? ($entry->schoolClass?->name ?? $entry->class_name) }}">
                                                        {{ $entry->teacher?->name ?? ($entry->schoolClass?->name ?? $entry->class_name) }}
                                                    </span>

                                                    @if ($entry->room)
                                                        <span class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-500 font-mono text-[8px] border border-zinc-200 dark:border-zinc-700 shrink-0">
                                                            {{ $entry->room }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            {{-- Empty Slot Cell --}}
                                            <div class="h-full min-h-[58px] rounded-lg border border-dashed border-zinc-200 dark:border-zinc-800 flex items-center justify-center text-zinc-300 dark:text-zinc-700">
                                                <span class="text-[9px] text-zinc-300 dark:text-zinc-700 font-mono">—</span>
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- 4. Official Notice Board Sign-off Validation Block --}}
            <div class="pt-4 border-t border-zinc-200 dark:border-zinc-800">
                <div class="grid grid-cols-3 gap-6 text-xs text-zinc-600 dark:text-zinc-400">
                    <div class="space-y-3">
                        <span class="font-bold uppercase tracking-wider text-[10px] text-zinc-500 block">Class Teacher</span>
                        <div class="border-b border-zinc-400 dark:border-zinc-600 h-6"></div>
                        <div class="text-[10px] text-zinc-400">Signature &bull; Date</div>
                    </div>
                    <div class="space-y-3">
                        <span class="font-bold uppercase tracking-wider text-[10px] text-zinc-500 block">Dean of Studies / Timetable Master</span>
                        <div class="border-b border-zinc-400 dark:border-zinc-600 h-6"></div>
                        <div class="text-[10px] text-zinc-400">Signature &bull; Date</div>
                    </div>
                    <div class="space-y-3">
                        <span class="font-bold uppercase tracking-wider text-[10px] text-zinc-500 block">Principal / Headteacher Stamp</span>
                        <div class="border-b border-zinc-400 dark:border-zinc-600 h-6"></div>
                        <div class="text-[10px] text-zinc-400">Official Stamp &bull; Signature</div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-800/60 flex items-center justify-between text-[10px] text-zinc-400 font-mono">
                    <span>Generated on {{ now()->format('d M Y, H:i') }} &bull; Solforbs School Management System</span>
                    <span>Classroom Notice Board Schedule</span>
                </div>
            </div>

        </div>
    </main>

</body>
</html>
