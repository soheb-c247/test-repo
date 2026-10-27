@php
    use App\Support\Age;
    use App\Support\NotesFormatter;

    $ageYears = Age::inYears($report->patient_age_value, $report->patient_age_unit);
    $sex = $report->patient_sex;
    $contact = array_filter([$lab['address'] ?? '', $lab['phone'] ? 'Tel: '.$lab['phone'] : '', $lab['email'] ?? '', $lab['website'] ?? '']);
    $fmt = fn ($date) => $date ? $date->format('d M Y, h:i A') : '—';
    $isBlank = fn ($v) => $v === null || $v === '' || $v === [];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report->report_number }} · {{ $lab['name'] }}</title>
    <style>
        @page { size: A4; margin: 128px 40px 78px 40px; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", Helvetica, Arial, sans-serif; font-size: 10px; color: #1e293b; margin: 0; line-height: 1.45; }
        body.screen { background: #fff; padding: 28px 34px; }

        .header { width: 100%; }
        .pdf .header { position: fixed; top: -108px; left: 0; right: 0; }
        .footer { width: 100%; font-size: 8px; color: #64748b; border-top: 1px solid #cbd5e1; padding-top: 6px; }
        .pdf .footer { position: fixed; bottom: -58px; left: 0; right: 0; }
        .screen .footer { margin-top: 24px; }

        table { border-collapse: collapse; width: 100%; }
        .brand td { vertical-align: middle; }
        .brand .name { font-size: 18px; font-weight: bold; color: #0b2a4a; letter-spacing: -0.2px; }
        .brand .name span { color: #0d847b; }
        .brand .tagline { font-size: 8.5px; color: #0d847b; text-transform: uppercase; letter-spacing: 1.6px; margin-top: 2px; }
        .brand .contact { text-align: right; font-size: 8.5px; color: #475569; }
        .rule { height: 3px; background: #0b2a4a; margin-top: 8px; }
        .rule-accent { height: 2px; background: #13a397; width: 30%; }

        .title { text-align: center; margin: 4px 0 10px; }
        .title h1 { font-size: 12px; letter-spacing: 3px; color: #0b2a4a; margin: 0; }
        .revision { border: 1px solid #c4b5fd; background: #f5f3ff; color: #4c1d95; padding: 6px 8px; margin-bottom: 10px; font-size: 9px; }

        .patient { border: 1px solid #cbd5e1; margin-bottom: 14px; }
        .patient td { padding: 4px 8px; font-size: 9.5px; vertical-align: top; }
        .patient .k { color: #64748b; width: 17%; white-space: nowrap; }
        .patient .v { font-weight: bold; color: #0f172a; width: 33%; }
        .patient tr + tr td { border-top: 1px solid #f1f5f9; }

        .test { margin-bottom: 14px; }
        .test-head { background: #f1f5f9; border-left: 3px solid #13a397; padding: 5px 8px; }
        .test-head .name { font-size: 11px; font-weight: bold; color: #0b2a4a; }
        .test-head .meta { font-size: 8.5px; color: #475569; }

        .results thead th { font-size: 8.5px; text-transform: uppercase; letter-spacing: .6px; color: #475569; text-align: left; padding: 5px 8px; border-bottom: 1px solid #94a3b8; }
        .results td { padding: 4px 8px; border-bottom: 1px solid #eef2f6; vertical-align: top; }
        .results .section td { padding-top: 8px; font-size: 8.5px; font-weight: bold; color: #0d847b; text-transform: uppercase; letter-spacing: .8px; border-bottom: none; }
        .results .param { width: 40%; }
        .results .result { width: 18%; color: #0f172a; }
        .results .unit { width: 14%; color: #475569; }
        .results .ref { width: 28%; color: #475569; }
        .method { display: block; font-size: 7.5px; color: #94a3b8; font-weight: normal; }
        .results .result.flagged { font-weight: bold; }
        .flag-arrow { padding-left: 3px; }
        .longtext { font-weight: normal; white-space: pre-line; }

        .subtable { margin: 2px 0 4px; }
        .subtable th, .subtable td { border: 1px solid #e2e8f0; padding: 3px 6px; text-align: left; font-size: 9px; }
        .subtable th { background: #f8fafc; color: #475569; font-size: 8px; text-transform: uppercase; letter-spacing: .5px; }

        .notes { margin-top: 6px; padding: 6px 8px; background: #f8fafc; border: 1px solid #e2e8f0; font-size: 8.5px; color: #334155; }
        .notes p { margin: 0 0 4px; }
        .notes p:last-child { margin-bottom: 0; }
        .notes-table { margin: 4px 0; width: auto; min-width: 60%; }
        .notes-table th, .notes-table td { border: 1px solid #cbd5e1; padding: 2px 8px; text-align: left; font-size: 8.5px; }
        .notes-table th { background: #eef2f6; }

        .clinical { margin: 8px 0; font-size: 9px; }
        .end { text-align: center; font-size: 9px; font-weight: bold; letter-spacing: 2px; color: #475569; margin: 14px 0 26px; }
        .signatures td { text-align: center; vertical-align: bottom; padding: 0 10px; }
        .signature-line { border-top: 1px solid #334155; margin: 34px auto 3px; width: 70%; }
        .signature-name { font-weight: bold; font-size: 9.5px; }
        .signature-title { font-size: 8.5px; color: #64748b; }

        .watermark { position: fixed; top: 38%; left: 8%; width: 84%; text-align: center; font-size: 96px; font-weight: bold; color: #ef4444; opacity: 0.08; transform: rotate(-30deg); letter-spacing: 12px; }
        .draft-banner { border: 1px solid #fdba74; background: #fff7ed; color: #9a3412; padding: 6px 8px; margin-bottom: 10px; font-size: 9px; font-weight: bold; text-align: center; }
        .avoid-break { page-break-inside: avoid; }
    </style>
</head>
<body class="{{ $forPdf ? 'pdf' : 'screen' }}">
@if ($isDraft)
    <div class="watermark">DRAFT</div>
@endif

<div class="header">
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
                @if (! empty($lab['registration_number']))
                    Reg. No.: {{ $lab['registration_number'] }}
                @endif
            </td>
        </tr>
    </table>
    <div class="rule"></div>
    <div class="rule-accent"></div>
</div>

<div class="footer">
    <table>
        <tr>
            <td style="width: 55%;">{{ $lab['report_footer'] }}</td>
            <td style="width: 25%; text-align: center;">{{ $report->report_number }}{{ $report->revision > 0 ? ' · Rev '.$report->revision : '' }}</td>
            <td style="width: 20%; text-align: right;">@unless ($forPdf)Printed {{ $generatedAt->format('d M Y H:i') }}@endunless{{-- PDF page numbers are stamped by ReportPdfService --}}</td>
        </tr>
    </table>
</div>

<main>
    <div class="title"><h1>LABORATORY REPORT</h1></div>

    @if ($isDraft)
        <div class="draft-banner">DRAFT — NOT VERIFIED — NOT FOR CLINICAL USE</div>
    @endif

    @if ($report->revision > 0 && ! $isDraft)
        <div class="revision">
            <strong>Revised report (revision {{ $report->revision }}).</strong> This report supersedes previously issued versions.
            @if ($report->correction_reason) Reason: {{ $report->correction_reason }} @endif
        </div>
    @endif

    <table class="patient">
        <tr>
            <td class="k">Patient name</td><td class="v">{{ $report->patient_name }}</td>
            <td class="k">Report no.</td><td class="v">{{ $report->report_number }}</td>
        </tr>
        <tr>
            <td class="k">Age / Sex</td><td class="v">{{ $report->patient_age_label }} / {{ $report->patient_sex_label }}</td>
            <td class="k">Registered on</td><td class="v">{{ $fmt($report->registered_at) }}</td>
        </tr>
        <tr>
            <td class="k">Referred by</td><td class="v">{{ $report->referring_doctor ?: '—' }}</td>
            <td class="k">Collected on</td><td class="v">{{ $fmt($report->collected_at) }}</td>
        </tr>
        <tr>
            <td class="k">Sample collected by</td><td class="v">{{ $report->collected_by ?: '—' }}</td>
            <td class="k">Reported on</td><td class="v">{{ $isDraft ? 'Pending' : $fmt($report->reported_at) }}</td>
        </tr>
        @if ($report->sample_id || $report->relationLoaded('patient') && $report->patient)
            <tr>
                <td class="k">Patient ID</td><td class="v">{{ $report->relationLoaded('patient') && $report->patient ? $report->patient->patient_code : '—' }}</td>
                <td class="k">Sample ID</td><td class="v">{{ $report->sample_id ?: '—' }}</td>
            </tr>
        @endif
    </table>

    @foreach ($items as $item)
        <div class="test">
            <div class="test-head avoid-break">
                <span class="name">{{ $item->test_name }}</span>
                <div class="meta">
                    {{ $item->category }}@if ($item->sample_type) &nbsp;·&nbsp; Specimen: {{ $item->sample_type }}@endif @if ($item->method) &nbsp;·&nbsp; Method: {{ $item->method }}@endif
                </div>
            </div>

            <table class="results">
                <thead>
                    <tr>
                        <th class="param">Test</th>
                        <th class="result">Result</th>
                        <th class="unit">Unit</th>
                        <th class="ref">Biological Ref. Interval</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($item->sections() as $section)
                        @php
                            $visible = collect($section['parameters'])->filter(function ($p) use ($item, $isBlank, $isDraft) {
                                if (! ($p['show_in_pdf'] ?? true)) return false;
                                $v = $item->results[$p['key']] ?? null;
                                return ! $isBlank($v) || ($p['required'] && $isDraft);
                            });
                        @endphp
                        @continue($visible->isEmpty())

                        @if ($section['title'] !== '' && (count($item->sections()) > 1 || strcasecmp($section['title'], $item->test_name) !== 0))
                            <tr class="section"><td colspan="4">{{ $section['title'] }}</td></tr>
                        @endif

                        @foreach ($visible as $parameter)
                            @php
                                $value = $item->results[$parameter['key']] ?? null;
                                $flag = $item->flags[$parameter['key']] ?? null;
                                $flagged = in_array($flag, ['high', 'low'], true);
                            @endphp

                            @if ($parameter['type'] === 'table')
                                <tr>
                                    <td colspan="4">
                                        <strong>{{ $parameter['label'] }}</strong>
                                        @if ($parameter['reference_text'])<span class="method" style="display:inline; padding-left:6px;">Reference: {{ $parameter['reference_text'] }}</span>@endif
                                        @if (! $isBlank($value))
                                            <table class="subtable">
                                                <tr>
                                                    <th>{{ $parameter['row_header'] ?? 'Item' }}</th>
                                                    @foreach ($parameter['columns'] as $column)<th>{{ $column['label'] }}</th>@endforeach
                                                </tr>
                                                @foreach ($value as $row)
                                                    <tr>
                                                        <td>{{ $row['label'] }}</td>
                                                        @foreach ($parameter['columns'] as $column)<td>{{ $row['values'][$column['key']] ?? '' }}</td>@endforeach
                                                    </tr>
                                                @endforeach
                                            </table>
                                        @else
                                            <span class="method">Not entered</span>
                                        @endif
                                    </td>
                                </tr>
                                @continue
                            @endif

                            <tr>
                                <td class="param">{{ $parameter['label'] }}@if ($parameter['method'])<span class="method">{{ $parameter['method'] }}</span>@endif</td>
                                @if ($parameter['type'] === 'textarea')
                                    <td class="result longtext" colspan="3">{{ $evaluator->formatValue($parameter, $value) ?: '—' }}</td>
                                @else
                                    <td @class(['result', 'flagged' => $flagged])>
                                        {{ $evaluator->formatValue($parameter, $value) ?: '—' }}@if ($flagged)<span class="flag-arrow">{{ $flag === 'high' ? '↑' : '↓' }}</span>@endif
                                    </td>
                                    <td class="unit">{{ $parameter['unit'] }}</td>
                                    <td class="ref">{{ $evaluator->referenceLabel($parameter, $sex, $ageYears) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>

            @if ($item->reporting_notes)
                <div class="notes">{!! NotesFormatter::toHtml($item->reporting_notes) !!}</div>
            @endif
        </div>
    @endforeach

    @if ($report->clinical_notes)
        <div class="clinical"><strong>Clinical notes:</strong> {{ $report->clinical_notes }}</div>
    @endif

    <div class="end">*** End of Report ***</div>

    <table class="signatures avoid-break">
        <tr>
            @forelse ($lab['signatories'] as $signatory)
                <td>
                    <div class="signature-line"></div>
                    <div class="signature-name">{{ $signatory['name'] }}</div>
                    <div class="signature-title">{{ $signatory['title'] }}</div>
                </td>
            @empty
                <td style="width: 60%;"></td>
                <td>
                    <div class="signature-line"></div>
                    <div class="signature-title">Authorised Signatory</div>
                </td>
            @endforelse
        </tr>
    </table>
</main>
</body>
</html>
