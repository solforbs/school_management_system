<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #1f2937;
        }

        .letterhead {
            width: 100%;
            border-bottom: 2px solid #1f2937;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }

        .letterhead td {
            vertical-align: middle;
        }

        .letterhead img {
            height: 56px;
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
            margin: 0 0 18px 0;
        }

        .class-block {
            margin-bottom: 22px;
        }

        /* Each class starts a fresh page so a timetable can be handed out
           per class without cutting one in half. */
        .class-block.page-break {
            page-break-before: always;
        }

        .class-name {
            font-size: 13px;
            font-weight: bold;
            margin: 0 0 6px 0;
            padding-bottom: 4px;
            border-bottom: 1px solid #d1d5db;
        }

        table.papers {
            width: 100%;
            border-collapse: collapse;
        }

        table.papers th {
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #4b5563;
            border-bottom: 1px solid #d1d5db;
            padding: 6px 4px;
        }

        table.papers td {
            padding: 6px 4px;
            border-bottom: 1px solid #f3f4f6;
        }

        table.papers .subject {
            font-weight: bold;
        }

        .muted {
            color: #9ca3af;
        }

        .empty {
            font-size: 11px;
            color: #4b5563;
        }

        .footer {
            margin-top: 24px;
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
        <div class="class-block {{ $index > 0 ? 'page-break' : '' }}">
            <p class="class-name">{{ $group['class_name'] }}</p>

            <table class="papers">
                <thead>
                    <tr>
                        <th style="width: 20%;">Date</th>
                        <th style="width: 18%;">Time</th>
                        <th style="width: 10%;">Duration</th>
                        <th>Subject</th>
                        <th>Paper</th>
                        <th style="width: 10%;">Marks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group['examinations'] as $examination)
                        <tr>
                            <td>
                                @if ($examination->exam_date)
                                    {{ $examination->exam_date->format('D, d M Y') }}
                                @else
                                    <span class="muted">Not scheduled</span>
                                @endif
                            </td>
                            <td>
                                @if ($examination->start_time && $examination->end_time)
                                    {{ $examination->start_time->format('H:i') }} –
                                    {{ $examination->end_time->format('H:i') }}
                                @elseif ($examination->start_time)
                                    From {{ $examination->start_time->format('H:i') }}
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>{{ $examination->durationLabel() ?? '—' }}</td>
                            <td class="subject">
                                {{ $examination->subject?->name ?? $examination->subject_name ?? '—' }}
                            </td>
                            <td>{{ $examination->title }}</td>
                            <td>{{ $examination->total_marks }}</td>
                        </tr>
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
