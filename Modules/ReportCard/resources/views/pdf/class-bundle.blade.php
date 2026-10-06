@php
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
            margin: 15px 22px;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5px;
            color: #1f2937;
            line-height: 1.25;
        }

        .preview-notice {
            margin: 0 0 6px 0;
            padding: 4px 6px;
            border: 1px solid #f59e0b;
            background-color: #fef3c7;
            color: #92400e;
            font-size: 8px;
            font-weight: bold;
            text-align: center;
            letter-spacing: 0.04em;
        }

        .letterhead {
            width: 100%;
            border-bottom: 2px solid #111827;
            padding-bottom: 4px;
        }

        .letterhead td {
            vertical-align: middle;
        }

        .letterhead img {
            height: 40px;
        }

        .institution-name {
            font-size: 14px;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
            text-align: center;
        }

        .institution-meta {
            font-size: 8px;
            color: #4b5563;
            margin: 1px 0 0 0;
            text-align: center;
        }

        .institution-email {
            font-size: 8px;
            color: #2563eb;
            margin: 1px 0 0 0;
            text-align: center;
        }

        .issued {
            font-size: 8px;
            color: #4b5563;
            text-align: right;
            white-space: nowrap;
        }

        .report-title {
            text-align: center;
            font-size: 10.5px;
            margin: 6px 0;
            padding-bottom: 4px;
            border-bottom: 1px solid #e5e7eb;
        }

        .identity {
            width: 100%;
            font-size: 8.5px;
            color: #4b5563;
            margin-bottom: 6px;
        }

        .identity strong {
            color: #111827;
        }

        table.subjects {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        table.subjects th,
        table.subjects td {
            border: 1px solid #d1d5db;
            padding: 3px 4.5px;
            font-size: 8px;
            text-align: center;
        }

        table.subjects th {
            background-color: #f3f4f6;
            font-size: 7.5px;
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
            font-size: 7.5px;
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
            font-size: 7px;
            color: #6b7280;
        }

        .muted {
            color: #9ca3af;
        }

        .summary-line {
            font-size: 8.5px;
            color: #4b5563;
            margin-bottom: 8px;
        }

        .summary-line strong {
            color: #111827;
        }

        .section-title {
            font-size: 8px;
            color: #6b7280;
            text-align: center;
            font-style: italic;
            margin: 0 0 3px 0;
        }

        .chart {
            width: 100%;
            border: 1px solid #e5e7eb;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .chart td {
            vertical-align: bottom;
            text-align: center;
            padding: 0 2px;
            height: 40px;
        }

        .chart td.axis {
            height: auto;
            padding: 2px 2px 3px 2px;
            font-size: 7px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .bar {
            font-size: 0;
            line-height: 0;
        }

        .bar-value {
            font-size: 7px;
            line-height: 8px;
            color: #4b5563;
        }

        table.scale {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        table.scale td {
            border: 1px solid #e5e7eb;
            padding: 2.5px 4px;
            font-size: 7px;
        }

        table.scale td.key {
            font-weight: bold;
            width: 34px;
            text-align: center;
        }

        table.scale td.key.nested {
            font-weight: normal;
        }

        .prose {
            font-size: 8.5px;
            line-height: 1.3;
            margin-bottom: 6px;
        }

        .signature-line {
            border-top: 1px solid #111827;
            width: 180px;
            margin-top: 14px;
            padding-top: 2px;
            font-size: 8px;
        }

        .footer-note {
            margin-top: 8px;
            font-size: 7px;
            color: #9ca3af;
            text-align: center;
        }

        .bundle-page {
            width: 100%;
        }
    </style>
</head>

<body>
    @foreach ($cards as $idx => $card)
        <div class="bundle-page" style="{{ $idx > 0 ? 'page-break-before: always;' : '' }}">
            @include('reportcard::pdf._single-card', $card)
        </div>
    @endforeach
</body>

</html>
