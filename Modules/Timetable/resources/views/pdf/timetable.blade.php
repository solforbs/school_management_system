<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — Academic Schedule</title>
    <style>
        @page {
            size: a4 landscape;
            margin: 8mm 10mm;
        }

        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Arial, sans-serif;
            font-size: 10px;
            color: #18181b;
            line-height: 1.25;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        /* Letterhead & Header */
        .letterhead {
            width: 100%;
            border-bottom: 2.5px solid #0047FF;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }

        .letterhead td {
            vertical-align: middle;
        }

        .logo-box {
            width: 48px;
            height: 48px;
            background: #0047FF;
            color: #ffffff;
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            line-height: 48px;
            border-radius: 6px;
        }

        .institution-name {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            margin: 0;
        }

        .institution-meta {
            font-size: 9px;
            color: #64748b;
            margin: 2px 0 0 0;
        }

        .notice-badge {
            background: #0047FF;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .notice-term {
            font-size: 10px;
            font-weight: bold;
            color: #0047FF;
            margin-top: 3px;
        }

        .notice-date {
            font-size: 8px;
            color: #64748b;
            font-family: monospace;
        }

        /* Subheader Bar */
        .subhead-bar {
            width: 100%;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 3.5px solid #0047FF;
            border-radius: 6px;
            padding: 5px 8px;
            margin-bottom: 8px;
        }

        .subhead-bar td {
            vertical-align: middle;
        }

        .class-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            color: #0f172a;
        }

        .class-highlight {
            color: #0047FF;
        }

        .meta-pill {
            font-size: 8px;
            font-weight: bold;
            color: #0047FF;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 6px;
        }

        .teacher-pill {
            font-size: 8px;
            color: #3f3f46;
            background: #ffffff;
            border: 1px solid #d4d4d8;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 6px;
        }

        /* Timetable Grid Table */
        table.grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 10px;
        }

        table.grid th,
        table.grid td {
            border: 1px solid #d4d4d8;
            padding: 4px 3px;
            vertical-align: top;
            text-align: center;
        }

        table.grid th {
            background: #f4f4f5;
            color: #18181b;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
        }

        table.grid th.day-header {
            width: 72px;
            text-align: left;
            padding-left: 6px;
            background: #e4e4e7;
            font-size: 9px;
            vertical-align: middle;
        }

        .th-name {
            font-size: 8.5px;
            font-weight: bold;
            display: block;
        }

        .th-time {
            font-size: 7.5px;
            font-family: monospace;
            color: #71717a;
            display: block;
            margin-top: 1px;
        }

        /* Day Row Header */
        td.day-cell {
            background: #f4f4f5;
            font-weight: bold;
            font-size: 9px;
            text-align: left;
            padding-left: 6px;
            vertical-align: middle;
        }

        .day-name {
            font-size: 9.5px;
            font-weight: bold;
            color: #18181b;
        }

        .day-count {
            font-size: 7.5px;
            color: #71717a;
            font-weight: normal;
        }

        /* Break Column */
        th.break-header,
        td.break-cell {
            width: 38px;
            background: #f4f4f5;
            color: #52525b;
            vertical-align: middle;
            text-align: center;
        }

        .break-card {
            background: #e4e4e7;
            border: 1px solid #d4d4d8;
            border-radius: 4px;
            padding: 4px 1px;
        }

        .break-label {
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #3f3f46;
            display: block;
        }

        .break-time {
            font-size: 6.5px;
            color: #71717a;
            font-family: monospace;
            display: block;
            margin-top: 2px;
        }

        /* Lesson Card */
        .lesson-card {
            background: #ffffff;
            border: 1px solid #d4d4d8;
            border-left: 3px solid #4f46e5;
            border-radius: 4px;
            padding: 3px;
            text-align: left;
            min-height: 40px;
        }

        .lesson-card.double-card {
            border-left: 3px solid #7c3aed;
            background: #faf5ff;
        }

        .lesson-subject {
            font-size: 9px;
            font-weight: bold;
            color: #18181b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .double-badge {
            display: inline-block;
            font-size: 6.5px;
            font-weight: bold;
            background: #ede9fe;
            color: #6d28d9;
            border: 1px solid #ddd6fe;
            padding: 1px 3px;
            border-radius: 3px;
            margin-top: 2px;
        }

        .lesson-footer {
            margin-top: 3px;
            padding-top: 2px;
            border-top: 1px solid #f4f4f5;
            font-size: 7.5px;
            color: #52525b;
        }

        .teacher-name {
            font-size: 7.5px;
            color: #3f3f46;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .room-badge {
            float: right;
            font-size: 6.5px;
            font-family: monospace;
            color: #71717a;
            background: #f4f4f5;
            padding: 0 2px;
            border-radius: 2px;
        }

        .empty-slot {
            color: #d4d4d8;
            font-size: 9px;
            vertical-align: middle;
            text-align: center;
            padding-top: 14px;
        }

        /* Official Sign-Off Block */
        .signoff-table {
            width: 100%;
            margin-top: 8px;
            border-top: 1px solid #e4e4e7;
            padding-top: 8px;
        }

        .signoff-table td {
            width: 33.33%;
            vertical-align: top;
            padding: 0 10px;
        }

        .signoff-title {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            color: #71717a;
            letter-spacing: 0.05em;
            margin-bottom: 16px;
        }

        .signoff-line {
            border-bottom: 1px solid #71717a;
            height: 1px;
            margin-bottom: 3px;
        }

        .signoff-meta {
            font-size: 7.5px;
            color: #a1a1aa;
        }

        .footer-note {
            margin-top: 8px;
            padding-top: 4px;
            border-top: 1px solid #f4f4f5;
            font-size: 7.5px;
            color: #a1a1aa;
            font-family: monospace;
        }
    </style>
</head>
<body>

    {{-- Official Header & Letterhead --}}
    <table class="letterhead">
        <tr>
            <td style="width: 64px;">
                @if (! empty($logoDataUri))
                    <img src="{{ $logoDataUri }}" alt="Logo" height="48" style="height: 48px; max-width: 64px; object-fit: contain;">
                @else
                    <div class="logo-box">
                        {{ substr($institution?->name ?? 'SMS', 0, 2) }}
                    </div>
                @endif
            </td>
            <td>
                <h1 class="institution-name">{{ $institution?->name ?? config('app.name', 'School Management System') }}</h1>
                <div class="institution-meta">
                    @if ($institution?->address) {{ $institution->address }} &bull; @endif
                    @if ($institution?->phone_number) Tel: {{ $institution->phone_number }} &bull; @endif
                    Official School Academic Schedule
                </div>
            </td>
            <td style="text-align: right; width: 180px;">
                <span class="notice-badge">NOTICE BOARD COPY</span>
                <div class="notice-term">{{ $academicYear }} &bull; {{ $term }}</div>
                <div class="notice-date">Generated: {{ now()->format('d M Y') }}</div>
            </td>
        </tr>
    </table>

    {{-- Class & Teacher Metadata Subheader --}}
    <table class="subhead-bar">
        <tr>
            <td>
                <span class="class-title">
                    @if ($isTeacher)
                        Teacher: <span class="class-highlight">{{ $teacher->name }}</span>
                    @else
                        Class: <span class="class-highlight">{{ $selectedClass?->name }}</span>
                    @endif
                </span>
                @if ($selectedClass?->classTeacher && ! $isTeacher)
                    <span class="teacher-pill">Class Teacher: {{ $selectedClass->classTeacher->name }}</span>
                @endif
                @if ($selectedClass?->curriculum)
                    <span class="meta-pill">{{ $selectedClass->curriculum->systemLabel() }}</span>
                @endif
            </td>
            <td style="text-align: right; font-size: 8.5px; color: #52525b;">
                Routine: <strong>{{ count(collect($periods)->filter(fn($p) => empty($p['is_break']))) }} Lessons / Day</strong>
            </td>
        </tr>
    </table>

    {{-- Visual Day-by-Period Schedule Grid Table --}}
    <table class="grid">
        <thead>
            <tr>
                <th class="day-header">Day / Time</th>
                @foreach ($periods as $period)
                    @php
                        $isBreakSlot = is_array($period) && ! empty($period['is_break']);
                        $slotName = is_array($period) ? ($period['name'] ?? $period['time']) : $period;
                        $slotTime = is_array($period) ? $period['time'] : $period;
                    @endphp
                    <th class="{{ $isBreakSlot ? 'break-header' : '' }}">
                        <span class="th-name">{{ $slotName }}</span>
                        <span class="th-time">{{ $slotTime }}</span>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($days as $day)
                <tr>
                    <td class="day-cell">
                        <div class="day-name">{{ $day }}</div>
                        <div class="day-count">
                            @php
                                $dayCount = collect($periods)->filter(fn($p) => empty($p['is_break']) && ! empty($grid[$day][$p['time'] ?? $p]))->count();
                            @endphp
                            {{ $dayCount }} {{ Str::plural('lesson', $dayCount) }}
                        </div>
                    </td>

                    @foreach ($periods as $period)
                        @php
                            $isBreakSlot = is_array($period) && ! empty($period['is_break']);
                            $periodStr = is_array($period) ? $period['time'] : $period;
                            $periodNumber = is_array($period) ? ($period['period_number'] ?? null) : null;
                            $breakName = is_array($period) ? ($period['name'] ?? 'Break') : 'Break';
                            $entry = $isBreakSlot ? null : ($grid[$day][$periodStr] ?? ($periodNumber ? ($grid[$day]['p_' . $periodNumber] ?? null) : null));
                        @endphp

                        @if ($isBreakSlot)
                            <td class="break-cell">
                                <div class="break-card">
                                    <span class="break-label">{{ $breakName }}</span>
                                    <span class="break-time">{{ $periodStr }}</span>
                                </div>
                            </td>
                        @elseif ($entry)
                            @php
                                $isDouble = method_exists($entry, 'isDoubleLesson') && $entry->isDoubleLesson();
                                $isPart1 = method_exists($entry, 'isDoubleLessonPart1') && $entry->isDoubleLessonPart1();
                                $isPart2 = method_exists($entry, 'isDoubleLessonPart2') && $entry->isDoubleLessonPart2();
                            @endphp
                            <td>
                                <div class="lesson-card {{ $isDouble ? 'double-card' : '' }}">
                                    <div class="lesson-subject">{{ $entry->subject }}</div>
                                    @if ($isDouble)
                                        <span class="double-badge">{{ $isPart2 ? 'Double (2)' : '2x Double' }}</span>
                                    @endif
                                    <div class="lesson-footer">
                                        @if ($entry->room)
                                            <span class="room-badge">{{ $entry->room }}</span>
                                        @endif
                                        <span class="teacher-name">{{ $entry->teacher?->name ?? ($entry->schoolClass?->name ?? $entry->class_name) }}</span>
                                    </div>
                                </div>
                            </td>
                        @else
                            <td>
                                <div class="empty-slot">&mdash;</div>
                            </td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Official Three-Tier Stamp & Signature Blocks --}}
    <table class="signoff-table">
        <tr>
            <td>
                <div class="signoff-title">Class Teacher</div>
                <div class="signoff-line"></div>
                <div class="signoff-meta">Signature &bull; Date</div>
            </td>
            <td>
                <div class="signoff-title">Dean of Studies / Timetable Master</div>
                <div class="signoff-line"></div>
                <div class="signoff-meta">Signature &bull; Date</div>
            </td>
            <td>
                <div class="signoff-title">Principal / Headteacher Stamp</div>
                <div class="signoff-line"></div>
                <div class="signoff-meta">Official Stamp &bull; Signature</div>
            </td>
        </tr>
    </table>

    <table style="width: 100%;" class="footer-note">
        <tr>
            <td>Generated on {{ now()->format('d M Y, H:i') }} &bull; Solforbs School Management System</td>
            <td style="text-align: right;">Classroom Notice Board Schedule</td>
        </tr>
    </table>

</body>
</html>
