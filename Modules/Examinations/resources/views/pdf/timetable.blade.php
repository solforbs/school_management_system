<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #1f2937;
        }

        .letterhead {
            width: 100%;
            border-bottom: 2px solid #1f2937;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .letterhead td {
            vertical-align: middle;
        }

        .letterhead img {
            height: 54px;
        }

        .institution-name {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
        }

        .institution-meta {
            font-size: 10px;
            color: #4b5563;
            margin: 2px 0 0 0;
        }

        .report-title {
            font-size: 15px;
            font-weight: bold;
            margin: 0 0 2px 0;
        }

        .report-meta {
            font-size: 10px;
            color: #4b5563;
            margin: 0 0 16px 0;
        }

        .class-block {
            margin-bottom: 20px;
        }

        /* Each class starts a fresh page so a timetable can be handed out
           per class without cutting one in half. */
        .class-block.page-break {
            page-break-before: always;
        }

        .class-name {
            font-size: 13px;
            font-weight: bold;
            margin: 0 0 2px 0;
        }

        .class-meta {
            font-size: 9px;
            color: #6b7280;
            margin: 0 0 8px 0;
            padding-bottom: 4px;
            border-bottom: 1px solid #d1d5db;
        }

        table.grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.grid th,
        table.grid td {
            border: 1px solid #555;
            padding: 5px 4px;
            vertical-align: middle;
            text-align: center;
        }

        table.grid th {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #1f2937;
            background-color: #e5e7eb;
        }

        table.grid th.day-col {
            width: 104px;
            text-align: left;
        }

        td.day-cell {
            background-color: #f3f4f6;
            text-align: left;
        }

        td.day-cell .weekday {
            font-weight: bold;
            font-size: 10px;
        }

        td.day-cell .date {
            color: #4b5563;
            font-size: 9px;
        }

        tr.week-row td {
            background-color: #d1d5db;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #1f2937;
            padding: 4px 6px;
            text-align: left;
        }

        tr.week-row td .range {
            font-weight: normal;
            text-transform: none;
            letter-spacing: 0;
            color: #4b5563;
        }

        .paper {
            padding: 2px 0;
        }

        .paper + .paper {
            margin-top: 3px;
            border-top: 1px dashed #9ca3af;
            padding-top: 3px;
        }

        .paper .subject {
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
        }

        .paper .title {
            color: #4b5563;
            font-size: 8px;
        }

        .paper .facts {
            color: #6b7280;
            font-size: 8px;
        }

        .cell-empty {
            color: #9ca3af;
        }

        .empty {
            font-size: 11px;
            color: #4b5563;
        }

        .footer {
            margin-top: 20px;
            font-size: 9px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
        }
    </style>
</head>

<body>
    <table class="letterhead">
        <tr>
            <td style="width: 70px;">
                @if ($logoDataUri)
                    <img src="{{ $logoDataUri }}" alt="{{ $institution?->name }}">
                @endif
            </td>
            <td>
                <p class="institution-name">{{ $institution?->name }}</p>
                <p class="institution-meta">
                    @if ($institution?->code)
                        Code: {{ $institution->code }} &nbsp;|&nbsp;
                    @endif
                    @if ($institution?->phone)
                        Tel: {{ $institution->phone }} &nbsp;|&nbsp;
                    @endif
                    @if ($institution?->email)
                        {{ $institution->email }}
                    @endif
                </p>
            </td>
        </tr>
    </table>

    <p class="report-title">{{ $heading }}</p>
    <p class="report-meta">{{ $subheading }}</p>

    @forelse ($groups as $index => $group)
        @php
            $sessions = $group['sessions'];
            $weeks = $group['days']->groupBy('week');
        @endphp
        <div class="class-block {{ $index > 0 ? 'page-break' : '' }}">
            <p class="class-name">{{ $group['class_name'] }}</p>
            <p class="class-meta">
                {{ $group['examinations']->count() }} paper(s)
                @if ($group['starts_on'])
                    &middot; {{ $group['starts_on']->format('d M Y') }} &ndash; {{ $group['ends_on']->format('d M Y') }}
                @endif
                @if ($group['week_count'] > 1)
                    &middot; runs {{ $group['week_count'] }} weeks
                @endif
            </p>

            <table class="grid">
                <thead>
                    <tr>
                        <th class="day-col">Day / Date</th>
                        @foreach ($sessions as $session)
                            <th>{{ $session }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($weeks as $weekNumber => $weekDays)
                        @php $weekDated = $weekDays->pluck('date')->filter(); @endphp
                        <tr class="week-row">
                            <td colspan="{{ $sessions->count() + 1 }}">
                                @if ($weekNumber > 0)
                                    Week {{ $weekNumber }}
                                    @if ($weekDated->isNotEmpty())
                                        <span class="range">
                                            &middot; {{ $weekDated->first()->format('d M') }} &ndash; {{ $weekDated->last()->format('d M Y') }}
                                        </span>
                                    @endif
                                @else
                                    Not scheduled
                                @endif
                            </td>
                        </tr>

                        @foreach ($weekDays as $day)
                            <tr>
                                <td class="day-cell">
                                    @if ($day['date'])
                                        <div class="weekday">{{ $day['date']->format('l') }}</div>
                                        <div class="date">{{ $day['date']->format('d M Y') }}</div>
                                    @else
                                        <div class="weekday">Date TBA</div>
                                    @endif
                                </td>

                                @foreach ($sessions as $session)
                                    @php $cell = $day['cells']->get($session); @endphp
                                    <td>
                                        @forelse ($cell ?? [] as $examination)
                                            <div class="paper">
                                                <div class="subject">
                                                    {{ $examination->subject?->name ?? $examination->subject_name ?? $examination->title }}
                                                </div>
                                                <div class="title">{{ $examination->title }}</div>
                                                <div class="facts">
                                                    {{ $examination->durationLabel() ?? '—' }} &middot;
                                                    {{ $examination->total_marks }} mks
                                                </div>
                                            </div>
                                        @empty
                                            <div class="cell-empty">&mdash;</div>
                                        @endforelse
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p class="empty">No examinations match this term and sitting yet.</p>
    @endforelse

    <div class="footer">
        Generated {{ now()->format('d M Y H:i') }} · {{ $institution?->name }}
    </div>
</body>

</html>
