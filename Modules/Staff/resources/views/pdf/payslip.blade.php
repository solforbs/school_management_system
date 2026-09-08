<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    @php
        $staff = $payment->staff;
        $staffNo = $staff?->staff_number ?? 'EMP-S-'.str_pad((string) $payment->id, 3, '0', STR_PAD_LEFT);
        $voucherNo = 'PAY-'.str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT);
        $periodName = $payment->period?->format('F Y') ?? 'Period';
        $method = ucfirst(str_replace('_', ' ', (string) $payment->payment_method));
    @endphp
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
            margin-bottom: 16px;
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

        .doc-title {
            font-size: 14px;
            font-weight: bold;
            margin: 0 0 2px 0;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .doc-meta {
            font-size: 10px;
            color: #4b5563;
            margin: 0 0 16px 0;
        }

        table.block {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        table.block td {
            border: 1px solid #d1d5db;
            padding: 7px 9px;
            vertical-align: top;
        }

        .label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #6b7280;
            display: block;
            margin-bottom: 2px;
        }

        .value {
            font-size: 11px;
            font-weight: bold;
        }

        .value .sub {
            font-weight: normal;
            color: #6b7280;
            font-size: 9px;
        }

        table.finance {
            width: 100%;
            border-collapse: collapse;
        }

        table.finance th {
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #1f2937;
            background-color: #e5e7eb;
            border: 1px solid #9ca3af;
            padding: 6px 9px;
        }

        table.finance th.amount,
        table.finance td.amount {
            text-align: right;
        }

        table.finance td {
            border: 1px solid #d1d5db;
            padding: 6px 9px;
        }

        table.finance tr.total td {
            background-color: #f3f4f6;
            font-weight: bold;
            font-size: 12px;
            border-top: 2px solid #9ca3af;
        }

        .amount {
            font-family: 'DejaVu Sans Mono', monospace;
        }

        .add {
            color: #047857;
        }

        .sub-amt {
            color: #b91c1c;
        }

        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 34px;
        }

        .signatures td {
            width: 50%;
            padding-top: 26px;
            font-size: 9px;
            color: #6b7280;
        }

        .signatures .line {
            border-top: 1px solid #6b7280;
            padding-top: 4px;
            width: 78%;
        }

        .notes {
            margin-top: 16px;
            border: 1px solid #d1d5db;
            padding: 8px 10px;
            font-size: 10px;
            font-style: italic;
            color: #374151;
        }

        .footer {
            margin-top: 22px;
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
                <p class="institution-name">{{ $institution?->name ?? 'Institution' }}</p>
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

    <p class="doc-title">Salary Disbursement Voucher</p>
    <p class="doc-meta">
        Voucher #{{ $voucherNo }} &nbsp;&middot;&nbsp; Pay period: {{ $periodName }}
        &nbsp;&middot;&nbsp; Status: {{ ucfirst((string) $payment->status) }}
    </p>

    <table class="block">
        <tr>
            <td style="width: 40%;">
                <span class="label">Employee Name</span>
                <span class="value">{{ $staff?->name ?? 'Staff Member' }}<br>
                    <span class="sub">{{ $staffNo }}</span></span>
            </td>
            <td style="width: 32%;">
                <span class="label">Designation &amp; Department</span>
                <span class="value">{{ $staff?->job_title ?? 'Staff Personnel' }}<br>
                    <span class="sub">{{ $staff?->department ?? 'General' }}</span></span>
            </td>
            <td style="width: 28%;">
                <span class="label">Payment Method</span>
                <span class="value">{{ $method !== '' ? $method : '—' }}<br>
                    <span class="sub">Ref: {{ $payment->reference ?: 'System internal' }}</span></span>
            </td>
        </tr>
    </table>

    <table class="finance">
        <thead>
            <tr>
                <th>Earnings / Deductions</th>
                <th class="amount">Amount (KES)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Basic Gross Salary</td>
                <td class="amount">{{ number_format((float) $payment->gross_amount, 2) }}</td>
            </tr>
            <tr>
                <td>Allowances (Housing, Commuter, Responsibility)</td>
                <td class="amount add">+{{ number_format((float) $payment->allowances, 2) }}</td>
            </tr>
            <tr>
                <td>Statutory Deductions (PAYE, NSSF, SHIF, Housing Levy)</td>
                <td class="amount sub-amt">-{{ number_format((float) $payment->deductions, 2) }}</td>
            </tr>
            <tr class="total">
                <td>Net Pay (Take-Home Disbursement)</td>
                <td class="amount">KES {{ number_format((float) $payment->net_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($payment->notes)
        <div class="notes">
            <span class="label">Payroll Remarks</span>
            &ldquo;{{ $payment->notes }}&rdquo;
        </div>
    @endif

    <table class="signatures">
        <tr>
            <td>
                <div class="line">
                    Prepared &amp; logged by: {{ $payment->recordedBy?->name ?? 'Accounts Officer' }}
                </div>
            </td>
            <td>
                <div class="line">Received by (employee signature &amp; date)</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Generated {{ now()->format('D, d M Y H:i') }} &middot; {{ $institution?->name }}
        &middot; Voucher #{{ $voucherNo }}
    </div>
</body>

</html>
