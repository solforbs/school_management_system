@php
    $bandColours ??= [
        'EE' => '#059669',
        'ME' => '#2563eb',
        'AE' => '#d97706',
        'BE' => '#dc2626',
    ];

    $colourFor ??= fn (?string $band) => $bandColours[$band] ?? '#374151';

    $dash ??= '—';
    $trim ??= fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
@endphp

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

@php
    $isCbc = $curriculum?->isCbc() ?? false;
@endphp

<table class="subjects">
    <thead>
        <tr>
            <th style="text-align: left;">{{ $isCbc ? 'Learning Area' : 'Subject' }}</th>
            @if ($isCbc)
                <th>Strand / Competency Focus</th>
            @else
                <th>Marks</th>
                <th>Out Of</th>
                <th>Avg %</th>
            @endif
            <th>{{ $isCbc ? 'Formative Expectation Band' : 'Grade' }}</th>
            <th>Points</th>
            <th style="text-align: left;">Facilitator Remarks</th>
            <th>Sign</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td class="subject">{{ $row->name }}</td>
                @if ($isCbc)
                    <td class="muted">{{ $row->strand ?? $row->learningArea ?? $dash }}</td>
                @else
                    <td>{{ $row->marks !== null ? $trim($row->marks) : $dash }}</td>
                    <td>{{ $row->outOf !== null ? $trim($row->outOf) : $dash }}</td>
                    <td>{{ $row->percentage !== null ? number_format($row->percentage, 0) : $dash }}</td>
                @endif
                <td class="grade" style="color: {{ $colourFor($row->expectationBand()) }};">
                    {{ $row->expectationBand() ?? $row->grade() ?? $dash }}
                    @if ($row->achievementLevel() && $row->achievementLevel() !== $row->expectationBand())
                        <span class="level">{{ $row->achievementLevel() }}</span>
                    @endif
                </td>
                <td>{{ $row->points() ?? $dash }}</td>
                <td class="remark">{{ $row->remark() ?? '' }}</td>
                <td class="muted">{{ $row->teacherInitials ?? '' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $isCbc ? 6 : 8 }}">No learning areas recorded for this class.</td>
            </tr>
        @endforelse

        @if ($summary['subjects_assessed'] > 0)
            <tr class="totals">
                <td style="text-align: right;">Overall Assessment</td>
                @if ($isCbc)
                    <td class="muted">All Core Strands</td>
                @else
                    <td>{{ $trim($summary['total_marks'] ?? 0) }}</td>
                    <td colspan="2">Out of: {{ $trim($summary['total_out_of'] ?? 0) }}</td>
                @endif
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
                <td colspan="2" class="remark">{{ $isCbc ? 'Predominant Achievement Level' : 'Mean Points / Overall Grade' }}</td>
            </tr>
        @endif
    </tbody>
</table>

<p class="summary-line">
    @if ($isCbc)
        Performance Profile:
        <strong>{{ $summary['mean_points'] !== null ? $trim($summary['mean_points']).' / '.$pointsCeiling.' pts' : $dash }}</strong>
        &nbsp;&nbsp; Predominant Band:
        <strong>
            @php $meanBand = \Modules\ReportCard\Support\GradingScaleDefaults::bandFor($meanGrade); @endphp
            {{ $meanBand ?? $meanGrade ?? $dash }}{{ $meanBand && $meanGrade !== $meanBand ? ' ('.$meanGrade.')' : '' }}
        </strong>
        &nbsp;&nbsp; Learning Areas Assessed:
        <strong>{{ $summary['subjects_assessed'] }} of {{ $summary['subjects_total'] }}</strong>
    @else
        Mean Score:
        <strong>{{ $summary['mean_points'] !== null ? $trim($summary['mean_points']).' / '.$pointsCeiling : $dash }}</strong>
        @if ($meanPercentage !== null)
            &nbsp;&nbsp; Mean Percentage:
            <strong>{{ number_format($meanPercentage, 2).'%' }}</strong>
        @endif
        &nbsp;&nbsp; Overall Grade:
        <strong>
            @php $meanBand = \Modules\ReportCard\Support\GradingScaleDefaults::bandFor($meanGrade); @endphp
            {{ $meanBand ?? $meanGrade ?? $dash }}{{ $meanBand && $meanGrade !== $meanBand ? ' ('.$meanGrade.')' : '' }}
        </strong>
        &nbsp;&nbsp; Subjects assessed:
        <strong>{{ $summary['subjects_assessed'] }} of {{ $summary['subjects_total'] }}</strong>
    @endif
</p>

@if ($rows->isNotEmpty())
    <p class="section-title">Performance Analysis</p>

    <table class="chart">
        <tr>
            @foreach ($rows as $row)
                <td>
                    @if ($row->isAssessed())
                        @if ($row->percentage !== null)
                            <div class="bar-value">{{ number_format($row->percentage, 0) }}%</div>
                            <div class="bar"
                                style="height: {{ max(2, (int) round($row->percentage * 0.28)) }}px; background-color: {{ $colourFor($row->expectationBand()) }};">
                            </div>
                        @elseif ($row->points() !== null)
                            <div class="bar-value">{{ $row->expectationBand() ?? $row->points() }}</div>
                            <div class="bar"
                                style="height: {{ max(2, (int) round(($row->points() / max(1, $pointsCeiling)) * 28)) }}px; background-color: {{ $colourFor($row->expectationBand()) }};">
                            </div>
                        @else
                            <div class="bar-value muted">n/a</div>
                        @endif
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

@if ($isCbc && !empty($pathwayRecommendations['has_recommendations']))
    <p class="section-title">Senior School Career Pathways Guidance (Grades 10–12)</p>

    <table class="subjects" style="margin-bottom: 10px;">
        <thead>
            <tr>
                <th style="text-align: left; width: 34%;">Career Pathway Track</th>
                <th style="width: 16%;">Cluster Score</th>
                <th style="width: 26%;">Readiness Assessment</th>
                <th style="text-align: left;">Key Learning Area Strengths</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pathwayRecommendations['pathways'] as $p)
                @php
                    $isPrimary = ($pathwayRecommendations['primary']['id'] ?? '') === $p['id'];
                @endphp
                <tr @if($isPrimary) style="background-color: #f0fdf4;" @endif>
                    <td class="subject" style="text-align: left;">
                        {{ $p['name'] }}
                        @if ($isPrimary)
                            <span style="display: block; font-size: 8px; color: #059669; font-weight: bold;">
                                ★ Top Recommended Track
                            </span>
                        @endif
                    </td>
                    <td>
                        <strong>{{ $p['average_points'] !== null ? number_format($p['average_points'], 1).' / 8.0' : '—' }}</strong>
                    </td>
                    <td>
                        @php
                            $readinessColor = match($p['readiness_level']) {
                                'high' => '#059669',
                                'medium' => '#2563eb',
                                'low' => '#d97706',
                                default => '#6b7280',
                            };
                        @endphp
                        <span style="font-weight: bold; color: {{ $readinessColor }};">
                            {{ $p['readiness'] }}
                        </span>
                    </td>
                    <td class="remark">
                        @if (!empty($p['strengths']))
                            {{ collect($p['strengths'])->pluck('subject')->implode(', ') }}
                        @else
                            <span class="muted">Foundational stage</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if (!empty($pathwayRecommendations['statement']))
        <p style="font-size: 9px; color: #374151; background-color: #f9fafb; border: 1px solid #e5e7eb; padding: 6px 8px; margin-bottom: 12px; border-left: 3px solid #2563eb;">
            <strong>Guidance & Placement Note:</strong> {{ $pathwayRecommendations['statement'] }}
        </p>
    @endif
@endif

@if ($scaleBands->isNotEmpty())
    @php
        $defaults = \Modules\ReportCard\Support\GradingScaleDefaults::class;
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
