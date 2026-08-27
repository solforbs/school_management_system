@php
    /**
     * Colours for the four expectation bands. Kept here rather than in the
     * stylesheet because DomPDF has no CSS variables and each band needs
     * the same colour in three places - the grade cell, the chart bar and
     * the scale key.
     */
    $bandColours = [
        'EE' => '#059669',
        'ME' => '#2563eb',
        'AE' => '#d97706',
        'BE' => '#dc2626',
    ];

    $colourFor = fn (?string $band) => $bandColours[$band] ?? '#374151';

    $dash = '—';
    $trim = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 24px 28px;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #1f2937;
        }

        .preview-notice {
            margin: 0 0 10px 0;
            padding: 6px 8px;
            border: 1px solid #f59e0b;
            background-color: #fef3c7;
            color: #92400e;
            font-size: 9px;
            font-weight: bold;
            text-align: center;
            letter-spacing: 0.04em;
        }

        .letterhead {
            width: 100%;
            border-bottom: 2px solid #111827;
            padding-bottom: 8px;
        }

        .letterhead td {
            vertical-align: middle;
        }

        .letterhead img {
            height: 52px;
        }

        .institution-name {
            font-size: 17px;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
            text-align: center;
        }

        .institution-meta {
            font-size: 9px;
            color: #4b5563;
            margin: 2px 0 0 0;
            text-align: center;
        }

        .institution-email {
            font-size: 9px;
            color: #2563eb;
            margin: 2px 0 0 0;
            text-align: center;
        }

        .issued {
            font-size: 9px;
            color: #4b5563;
            text-align: right;
            white-space: nowrap;
        }

        .report-title {
            text-align: center;
            font-size: 12px;
            margin: 12px 0;
            padding-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
        }

        .identity {
            width: 100%;
            font-size: 10px;
            color: #4b5563;
            margin-bottom: 10px;
        }

        .identity strong {
            color: #111827;
        }

        table.subjects {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        table.subjects th,
        table.subjects td {
            border: 1px solid #d1d5db;
            padding: 5px 6px;
            font-size: 10px;
            text-align: center;
        }

        table.subjects th {
            background-color: #f3f4f6;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        table.subjects td.subject,
        table.subjects td.remark {
            text-align: left;
        }

        table.subjects td.subject {
            font-weight: bold;
        }

        table.subjects td.remark {
            font-size: 9px;
            color: #4b5563;
        }

        table.subjects tr.totals td {
            background-color: #f3f4f6;
            font-weight: bold;
        }

        .grade {
            font-weight: bold;
        }

        .level {
            display: block;
            font-weight: normal;
            font-size: 8px;
            color: #6b7280;
        }

        .muted {
            color: #9ca3af;
        }

        .summary-line {
            font-size: 10px;
            color: #4b5563;
            margin-bottom: 14px;
        }

        .summary-line strong {
            color: #111827;
        }

        .section-title {
            font-size: 9px;
            color: #6b7280;
            text-align: center;
            font-style: italic;
            margin: 0 0 6px 0;
        }

        .chart {
            width: 100%;
            border: 1px solid #e5e7eb;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .chart td {
            vertical-align: bottom;
            text-align: center;
            padding: 0 4px;
            height: 110px;
        }

        .chart td.axis {
            height: auto;
            padding: 3px 4px 5px 4px;
            font-size: 8px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .bar {
            font-size: 0;
            line-height: 0;
        }

        .bar-value {
            font-size: 8px;
            line-height: 10px;
            color: #4b5563;
        }

        table.scale {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        table.scale td {
            border: 1px solid #e5e7eb;
            padding: 4px 6px;
            font-size: 8px;
        }

        table.scale td.key {
            font-weight: bold;
            width: 42px;
            text-align: center;
        }

        table.scale td.key.nested {
            font-weight: normal;
        }

        .prose {
            font-size: 10px;
            line-height: 1.5;
            margin-bottom: 12px;
        }

        .signature-line {
            border-top: 1px solid #111827;
            width: 200px;
            margin-top: 26px;
            padding-top: 3px;
            font-size: 9px;
        }

        .footer-note {
            margin-top: 16px;
            font-size: 8px;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>

<body>
    {{-- Only ever set by the settings preview. A real report card never
         carries this, so there is no way to mistake one for the other. --}}
    @if (!empty($previewNotice))
        <p class="preview-notice">{{ $previewNotice }}</p>
    @endif

    <table class="letterhead">
        <tr>
            <td style="width: 70px;">
                @if ($logoDataUri)
                    <img src="{{ $logoDataUri }}" alt="{{ $institution->name }}">
                @endif
            </td>
            <td>
                <p class="institution-name">{{ $institution->name }}</p>
                @if ($institution->physical_address || $institution->postal_address)
                    <p class="institution-meta">
                        {{ collect([$institution->physical_address, $institution->postal_address, $institution->city])->filter()->implode(', ') }}
                    </p>
                @endif
                @if ($institution->phone || $institution->alternate_phone)
                    <p class="institution-meta">
                        Tel: {{ collect([$institution->phone, $institution->alternate_phone])->filter()->implode(' / ') }}
                    </p>
                @endif
                @if ($institution->email)
                    <p class="institution-email">{{ $institution->email }}</p>
                @endif
            </td>
            <td style="width: 90px;">
                <p class="issued">Date: {{ now()->format('j F Y') }}</p>
            </td>
        </tr>
    </table>

    <p class="report-title">
        Report Form For: <strong>{{ strtoupper($reportCard->term) }}</strong>
        {{-- Only where the term's name doesn't already say which one it is,
             so a report for "Term 1" isn't headed "TERM 1 | TERM 1". --}}
        @if ($reportCard->term_number && ! str_contains(strtolower($reportCard->term), (string) $reportCard->term_number))
            | <strong>TERM {{ $reportCard->term_number }}</strong>
        @endif
        @if ($reportCard->academic_year)
            | <strong>Year: {{ $reportCard->academic_year }}</strong>
        @endif
    </p>

    <table class="identity">
        <tr>
            <td>
                Student Name: <strong>{{ strtoupper($student->name) }}</strong>
                @if ($studentDetails?->gender)
                    ({{ ucfirst($studentDetails->gender) }})
                @endif
            </td>
            <td>Adm No: <strong>{{ $studentDetails?->admission_number ?? $dash }}</strong></td>
            <td>Class: <strong>{{ $reportCard->schoolClass?->name ?? $dash }}</strong></td>
            <td>UPI/NEMIS: <strong>{{ $studentDetails?->student_number ?? $dash }}</strong></td>
        </tr>
    </table>

    <table class="subjects">
        <thead>
            <tr>
                <th style="text-align: left;">Subject</th>
                <th>Marks</th>
                <th>Out Of</th>
                <th>Avg %</th>
                <th>Grade</th>
                <th>Points</th>
                <th style="text-align: left;">Remarks</th>
                <th>Sign</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="subject">{{ $row->name }}</td>
                    <td>{{ $row->isAssessed() ? $trim($row->marks) : $dash }}</td>
                    <td>{{ $row->isAssessed() ? $trim($row->outOf) : $dash }}</td>
                    <td>{{ $row->isAssessed() ? number_format($row->percentage, 0) : $dash }}</td>
                    {{-- Band and level together: the band is the language a parent
                         reads, the level is what KJSEA records. --}}
                    <td class="grade" style="color: {{ $colourFor($row->expectationBand()) }};">
                        {{ $row->expectationBand() ?? $row->grade() ?? $dash }}
                        @if ($row->achievementLevel())
                            <span class="level">{{ $row->achievementLevel() }}</span>
                        @endif
                    </td>
                    <td>{{ $row->points() ?? $dash }}</td>
                    <td class="remark">{{ $row->remark() ?? '' }}</td>
                    <td class="muted">{{ $row->teacherInitials ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No subjects recorded for this class.</td>
                </tr>
            @endforelse

            @if ($summary['subjects_assessed'] > 0)
                <tr class="totals">
                    <td style="text-align: right;">Total</td>
                    <td>{{ $trim($summary['total_marks']) }}</td>
                    <td colspan="2">Out of: {{ $trim($summary['total_out_of']) }}</td>
                    @php
                        $overallGrade = $summary['band']?->grade;
                        $overallBand = \Modules\ReportCard\Support\GradingScaleDefaults::bandFor($overallGrade);
                    @endphp
                    <td class="grade" style="color: {{ $colourFor($overallBand) }};">
                        {{ $overallBand ?? $overallGrade ?? $dash }}
                        @if ($overallBand && $overallGrade !== $overallBand)
                            <span class="level">{{ $overallGrade }}</span>
                        @endif
                    </td>
                    <td>{{ $summary['mean_points'] !== null ? $trim($summary['mean_points']) : $dash }}</td>
                    <td colspan="2" class="remark">Mean points / Overall band</td>
                </tr>
            @endif
        </tbody>
    </table>

    <p class="summary-line">
        Mean Score:
        <strong>{{ $summary['mean_points'] !== null ? $trim($summary['mean_points']).' / '.$pointsCeiling : $dash }}</strong>
        &nbsp;&nbsp; Mean Percentage:
        <strong>{{ $meanPercentage !== null ? number_format($meanPercentage, 2).'%' : $dash }}</strong>
        &nbsp;&nbsp; Overall Grade:
        <strong>
            @php $meanBand = \Modules\ReportCard\Support\GradingScaleDefaults::bandFor($meanGrade); @endphp
            {{ $meanBand ?? $meanGrade ?? $dash }}{{ $meanBand && $meanGrade !== $meanBand ? ' ('.$meanGrade.')' : '' }}
        </strong>
        &nbsp;&nbsp; Subjects assessed:
        <strong>{{ $summary['subjects_assessed'] }} of {{ $summary['subjects_total'] }}</strong>
    </p>

    @if ($rows->isNotEmpty())
        <p class="section-title">Performance Analysis</p>

        <table class="chart">
            <tr>
                @foreach ($rows as $row)
                    <td>
                        @if ($row->isAssessed())
                            {{-- 90px is the tallest a bar gets, so 100% fills the plot area
                                 and everything else is read against it. --}}
                            <div class="bar-value">{{ number_format($row->percentage, 0) }}%</div>
                            <div class="bar"
                                style="height: {{ max(2, (int) round($row->percentage * 0.9)) }}px; background-color: {{ $colourFor($row->expectationBand()) }};">
                            </div>
                        @else
                            <div class="bar-value muted">n/a</div>
                        @endif
                    </td>
                @endforeach
            </tr>
            <tr>
                @foreach ($rows as $row)
                    <td class="axis">{{ $row->shortLabel() }}</td>
                @endforeach
            </tr>
        </table>
    @endif

    @if ($scaleBands->isNotEmpty())
        @php
            $defaults = \Modules\ReportCard\Support\GradingScaleDefaults::class;
            // Levels that roll up into an expectation band are grouped under
            // it, so the key shows both at once. An 8-4-4 scale has no bands
            // to group by and falls into "" — one flat run, as before.
            $grouped = $scaleBands->groupBy(fn ($band) => $defaults::bandFor($band->grade) ?? '');
        @endphp

        <p class="section-title">
            Grading Scale{{ $curriculum ? ' — '.$curriculum->name.' · '.$curriculum->gradesLabel() : '' }}
        </p>

        <table class="scale">
            @foreach ($grouped as $bandLetters => $bands)
                @if ($bandLetters !== '')
                    <tr>
                        <td class="key" style="color: {{ $colourFor($bandLetters) }};">{{ $bandLetters }}</td>
                        <td colspan="7">{{ $defaults::bandDescription($bandLetters) }}</td>
                    </tr>
                @endif

                @foreach ($bands->chunk(4) as $chunk)
                    <tr>
                        @foreach ($chunk as $band)
                            <td class="key {{ $bandLetters !== '' ? 'nested' : '' }}"
                                style="color: {{ $colourFor($bandLetters ?: null) }};">{{ $band->grade }}</td>
                            <td>
                                {{ $trim($band->min_percentage) }}–{{ $trim($band->max_percentage) }}%
                                @if ($band->remark)
                                    · {{ $band->remark }}
                                @endif
                            </td>
                        @endforeach

                        {{-- Pad the last row so its cells keep the same width as the rows above. --}}
                        @for ($i = $chunk->count(); $i < 4; $i++)
                            <td class="key"></td>
                            <td></td>
                        @endfor
                    </tr>
                @endforeach
            @endforeach
        </table>
    @endif

    <div class="prose">{!! $openingHtml !!}</div>

    @if ($termHistory->count() > 1)
        <p class="section-title">Performance Against Last Term</p>

        <table class="subjects">
            <thead>
                <tr>
                    <th style="text-align: left;">Term</th>
                    <th>Mean Percentage</th>
                    <th>Overall Grade</th>
                    <th>Change</th>
                </tr>
            </thead>
            <tbody>
                @php $previous = null; @endphp
                @foreach ($termHistory as $entry)
                    @php
                        $delta = ($previous && $entry->mean_percentage !== null && $previous->mean_percentage !== null)
                            ? round($entry->mean_percentage - $previous->mean_percentage, 2)
                            : null;
                    @endphp
                    <tr>
                        {{-- The year is spelled out because the previous term can sit in
                             the year before this one, when this report is for Term 1. --}}
                        <td class="subject">{{ $entry->term }} {{ $entry->academic_year }}</td>
                        <td>{{ $entry->mean_percentage !== null ? number_format($entry->mean_percentage, 2).'%' : $dash }}</td>
                        <td>{{ $entry->mean_grade ?? $dash }}</td>
                        <td>
                            @if ($delta === null)
                                {{ $dash }}
                            @elseif ($delta > 0)
                                &#9650; Improved by {{ number_format(abs($delta), 2) }} pts
                            @elseif ($delta < 0)
                                &#9660; Declined by {{ number_format(abs($delta), 2) }} pts
                            @else
                                No change
                            @endif
                        </td>
                    </tr>
                    @php $previous = $entry; @endphp
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="prose">{!! $closingHtml !!}</div>

    @if ($signatoryName)
        <div class="signature-line">
            {{ $signatoryName }}
            @if ($signatoryTitle)
                <br>{{ $signatoryTitle }}
            @endif
        </div>
    @endif

    <p class="footer-note">
        This report card was generated automatically by {{ $institution->name }}'s school management system.
    </p>
</body>

</html>
