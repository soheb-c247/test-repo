@php
    $contact = array_filter([$lab['address'] ?? '', $lab['phone'] ? 'Tel: '.$lab['phone'] : '', $lab['email'] ?? '', $lab['website'] ?? '']);
    $money = fn ($v) => $currency.number_format((float) $v, 2);
    $fmt = fn ($date) => $date ? $date->format('d M Y, h:i A') : '—';
    $report = $bill->report;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $bill->bill_number }} · {{ $lab['name'] }}</title>
    <style>
        @page { size: A4; margin: 36px 40px; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", Helvetica, Arial, sans-serif; font-size: 10px; color: #1e293b; margin: 0; line-height: 1.45; }
        body.screen { background: #fff; padding: 28px 34px; max-width: 820px; margin: 0 auto; }

        table { border-collapse: collapse; width: 100%; }
        .brand td { vertical-align: middle; }
        .brand .name { font-size: 18px; font-weight: bold; color: #0b2a4a; letter-spacing: -0.2px; }
        .brand .name span { color: #0d847b; }
        .brand .tagline { font-size: 8.5px; color: #0d847b; text-transform: uppercase; letter-spacing: 1.6px; margin-top: 2px; }
        .brand .contact { text-align: right; font-size: 8.5px; color: #475569; }
        .rule { height: 3px; background: #0b2a4a; margin-top: 8px; }
        .rule-accent { height: 2px; background: #13a397; width: 30%; }

        .title { text-align: center; margin: 14px 0 10px; }
        .title h1 { font-size: 12px; letter-spacing: 3px; color: #0b2a4a; margin: 0; }
        .cancelled { border: 1px solid #fca5a5; background: #fef2f2; color: #991b1b; padding: 6px 8px; margin-bottom: 10px; font-size: 9px; font-weight: bold; text-align: center; }

        .meta { border: 1px solid #cbd5e1; margin-bottom: 14px; }
        .meta td { padding: 4px 8px; font-size: 9.5px; vertical-align: top; }
        .meta .k { color: #64748b; width: 17%; white-space: nowrap; }
        .meta .v { font-weight: bold; color: #0f172a; width: 33%; }
        .meta tr + tr td { border-top: 1px solid #f1f5f9; }

        .lines thead th { font-size: 8.5px; text-transform: uppercase; letter-spacing: .6px; color: #475569; text-align: left; padding: 5px 8px; border-bottom: 1px solid #94a3b8; }
        .lines td { padding: 5px 8px; border-bottom: 1px solid #eef2f6; }
        .num { text-align: right; white-space: nowrap; }
        .code { color: #64748b; font-size: 8.5px; }

        .totals { width: 46%; margin-left: 54%; margin-top: 8px; }
        .totals td { padding: 3px 8px; }
        .totals .grand td { border-top: 1px solid #94a3b8; font-weight: bold; font-size: 11px; color: #0b2a4a; padding-top: 5px; }
        .totals .due td { font-weight: bold; }

        h2 { font-size: 9px; letter-spacing: 1.5px; text-transform: uppercase; color: #0d847b; margin: 18px 0 4px; }
        .notes { margin-top: 10px; font-size: 9px; color: #334155; }
        .footer { margin-top: 28px; border-top: 1px solid #cbd5e1; padding-top: 6px; font-size: 8px; color: #64748b; }
    </style>
</head>
<body class="{{ $forPdf ? 'pdf' : 'screen' }}">
<table class="brand">
    <tr>
        <td style="width: 54px;"><img src="{{ $logo }}" alt="" style="width: 46px; height: 46px;"></td>
        <td>
            <div class="name">@if (str_starts_with($lab['name'], 'AccuCare'))Accu<span>Care</span>{{ substr($lab['name'], 8) }}@else{{ $lab['name'] }}@endif</div>
            <div class="tagline">{{ $lab['tagline'] }}</div>
        </td>
        <td class="contact">
            @foreach ($contact as $line)
                {{ $line }}<br>
            @endforeach
        </td>
    </tr>
</table>
<div class="rule"></div>
<div class="rule-accent"></div>

<div class="title"><h1>BILL / RECEIPT</h1></div>

@if ($bill->isCancelled())
    <div class="cancelled">CANCELLED{{ $bill->cancel_reason ? ' — '.$bill->cancel_reason : '' }}</div>
@endif

<table class="meta">
    <tr>
        <td class="k">Patient name</td><td class="v">{{ $bill->patient_name }}</td>
        <td class="k">Bill no.</td><td class="v">{{ $bill->bill_number }}</td>
    </tr>
    <tr>
        <td class="k">Patient ID</td><td class="v">{{ $bill->patient?->patient_code ?: '—' }}</td>
        <td class="k">Bill date</td><td class="v">{{ $fmt($bill->issued_at) }}</td>
    </tr>
    <tr>
        <td class="k">Age / Sex</td><td class="v">{{ $report ? $report->patient_age_label.' / '.$report->patient_sex_label : '—' }}</td>
        <td class="k">Report no.</td><td class="v">{{ $report?->report_number ?? '—' }}</td>
    </tr>
    <tr>
        <td class="k">Referred by</td><td class="v">{{ $report?->referring_doctor ?: '—' }}</td>
        <td class="k">Status</td><td class="v">{{ $bill->status_label }}</td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th style="width: 8%;">#</th>
            <th>Test</th>
            <th class="num" style="width: 22%;">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($bill->items as $line)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $line->description }} @if ($line->test_code)<span class="code">({{ $line->test_code }})</span>@endif</td>
                <td class="num">{{ $money($line->amount) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>Subtotal</td><td class="num">{{ $money($bill->subtotal) }}</td></tr>
    @if ((float) $bill->discount_amount > 0)
        <tr><td>Discount{{ $bill->discount_type === 'percent' ? ' ('.rtrim(rtrim(number_format((float) $bill->discount_value, 2), '0'), '.').'%)' : '' }}</td><td class="num">− {{ $money($bill->discount_amount) }}</td></tr>
    @endif
    <tr class="grand"><td>Total</td><td class="num">{{ $money($bill->total) }}</td></tr>
    <tr><td>Paid</td><td class="num">{{ $money($bill->amount_paid) }}</td></tr>
    <tr class="due"><td>Balance due</td><td class="num">{{ $money($bill->balance) }}</td></tr>
</table>

@if ($bill->payments->isNotEmpty())
    <h2>Payments received</h2>
    <table class="lines">
        <thead>
            <tr>
                <th>Date</th>
                <th>Method</th>
                <th>Reference</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bill->payments as $payment)
                <tr>
                    <td>{{ $fmt($payment->paid_at) }}</td>
                    <td>{{ $payment->method_label }}</td>
                    <td>{{ $payment->reference ?: '—' }}</td>
                    <td class="num">{{ $money($payment->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($bill->notes)
    <div class="notes"><strong>Notes:</strong> {{ $bill->notes }}</div>
@endif

<div class="footer">
    This is a computer-generated bill. Printed {{ $generatedAt->format('d M Y, h:i A') }}.
</div>
</body>
</html>
