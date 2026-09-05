<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 24px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #18181b; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .meta { color: #71717a; font-size: 9px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #e5e7eb;
            text-align: left;
            padding: 6px 8px;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .02em;
            border-bottom: 1px solid #d4d4d8;
        }
        tbody td {
            padding: 5px 8px;
            border-bottom: 1px solid #f0f0f2;
        }
        tbody tr:nth-child(even) { background: #fafafa; }
        .empty { padding: 16px 8px; color: #a1a1aa; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">Generated {{ $generatedAt }} &middot; {{ count($rows) }} {{ Str::plural('record', count($rows)) }}</div>

    <table>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="empty" colspan="{{ count($headers) }}">No records to show.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
