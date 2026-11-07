@php
    use App\Support\Age;
    use App\Support\NotesFormatter;

    $ageYears = Age::inYears($report->patient_age_value, $report->patient_age_unit);
    $sex = $report->patient_sex;
    $fmt = fn ($date) => $date ? $date->format('d M Y, h:i A') : '—';
    // Small round green icons for the letterhead (SVG data URIs render in both browsers and DomPDF).
    $icon = function (string $glyph) {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="#1f4d27"/>'.$glyph.'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    };
    $icons = [
        'phone' => $icon('<path transform="translate(4.5 4.5) scale(.62)" fill="#fff" d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.6 21 3 13.4 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1l-2.3 2.2z"/>'),
        'clock' => $icon('<circle cx="12" cy="12" r="6.2" fill="none" stroke="#fff" stroke-width="1.6"/><path d="M12 8.6V12l2.4 1.6" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>'),
        'home' => $icon('<circle cx="8.2" cy="15.6" r="1.9" fill="none" stroke="#fff" stroke-width="1.4"/><circle cx="16.6" cy="15.6" r="1.9" fill="none" stroke="#fff" stroke-width="1.4"/><path d="M10 15.6h3.4l1.5-5.4h-2.2M14.9 10.2h1.8l1.3 3.4M6 9h3.4" fill="none" stroke="#fff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>'),
        'pin' => $icon('<path transform="translate(5.6 4.8) scale(.54)" fill="#fff" d="M12 2C8.1 2 5 5.1 5 9c0 5.2 7 13 7 13s7-7.8 7-13c0-3.9-3.1-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/>'),
    ];
    $isBlank = fn ($v) => $v === null || $v === '' || $v === [];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report->report_number }} · {{ $lab['name'] }}</title>
    <style>
        /*
         * A4 portrait = 297mm. Page margins reserve the header (top) and footer (bottom) on every
         * page so that content flows in exactly 8.5in (215.9mm):
         *   46mm header + 215.9mm content + 35.1mm footer = 297mm
         */
        @page { size: 210mm 297mm; margin: 46mm 14mm 35.1mm 14mm; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", Helvetica, Arial, sans-serif; font-size: 10px; color: #1e293b; margin: 0; line-height: 1.45; }
        body.screen { background: #fff; padding: 28px 34px; min-height: 297mm; display: flex; flex-direction: column; }
        .screen main { flex: 1 0 auto; }

        .header { width: 100%; height: 44mm; overflow: hidden; }
        .footer { width: 100%; height: 29mm; overflow: hidden; font-size: 8px; color: #64748b; border-top: 1px solid #cbd5e1; padding-top: 6px; }
        .pdf .header { position: fixed; top: -46mm; left: 0; right: 0; }
        .pdf .footer { position: fixed; bottom: -35.1mm; left: 0; right: 0; }
        .screen .header { margin-bottom: 8px; }
        .screen .footer { height: auto; margin-top: 24px; }
        @media print {
            body.screen { padding: 0; }
            .screen .header { position: fixed; top: -46mm; left: 0; right: 0; margin: 0; }
            .screen .footer { position: fixed; bottom: -35.1mm; left: 0; right: 0; height: 29mm; margin: 0; }
        }

        table { border-collapse: collapse; width: 100%; }

        /* Letterhead (header + footer artwork). Hidden — but its space stays reserved — when pre-printed stationery is used. */
        .letterhead-off .lh { visibility: hidden; }
        .lh-top { height: 3mm; background: #1f4d27; border-bottom: 1px solid #13a397; margin-bottom: 2mm; }
        .lh-brand td { vertical-align: middle; }
        .lh-name { font-size: 40px; font-weight: bold; color: #2b5d2f; letter-spacing: -1.5px; line-height: 1; }
        .lh-name span { color: #1f4d27; }
        .lh-sub { margin: 3px 0 0 6px; }
        .lh-sub td { vertical-align: middle; padding: 0; }
        .lh-sub .ln div { height: 2px; background: #1f4d27; }
        .lh-sub .ln { width: 16px; }
        .lh-sub .tx { text-align: center; font-size: 11px; font-weight: bold; color: #1f4d27; letter-spacing: 3.2px; padding: 0 5px; white-space: nowrap; }
        .lh-tag { font-family: "DejaVu Serif", serif; font-size: 12.5px; font-style: italic; font-weight: bold; color: #1f4d27; margin: 7px 0 0 8px; }
        .lh-contact { border-left: 2px solid #1f4d27; padding-left: 10px; }
        .lh-contact table td { padding: 2.5px 0; vertical-align: middle; font-size: 10px; font-weight: bold; color: #1f4d27; line-height: 1.25; }
        .lh-contact .ic { width: 24px; }
        .lh-contact .ic img { width: 17px; height: 17px; }
        .lh-contact small { display: block; font-size: 7.5px; color: #64748b; font-weight: bold; text-transform: uppercase; letter-spacing: .6px; }
        .lh-rule { height: 3px; background: #1f4d27; margin-top: 5px; }

        .wm { position: fixed; top: 62mm; left: 36mm; width: 110mm; height: 88mm; }
        .wm img { width: 110mm; height: 88mm; }
        .screen .wm { position: absolute; top: 330px; left: 50%; margin-left: -55mm; }
        body.screen { position: relative; }
        .lh-quote { background: #1f4d27; border-top: 2px solid #13a397; margin-top: 4px; }
        .lh-quote td { padding: 7px 6px; vertical-align: middle; color: #fff; }
        .lh-quote .qm { width: 5%; text-align: center; font-size: 15px; font-weight: bold; color: #7fd1c7; font-family: "DejaVu Serif", serif; line-height: 1; }
        .lh-quote .qt { text-align: center; font-size: 10px; font-style: italic; letter-spacing: .4px; }

        .title { text-align: center; margin: 4px 0 10px; }
        .title h1 { font-size: 12px; letter-spacing: 3px; color: #0b2a4a; margin: 0; }
        .revision { border: 1px solid #c4b5fd; background: #f5f3ff; color: #4c1d95; padding: 6px 8px; margin-bottom: 10px; font-size: 9px; }

        .patient { border: 1px solid #c3d9c8; margin-bottom: 14px; }
        .patient td { padding: 4px 8px; font-size: 9.5px; vertical-align: top; }
        .patient .k { color: #5b7a61; width: 17%; white-space: nowrap; }
        .patient .v { font-weight: bold; color: #0f172a; width: 33%; }
        .patient tr + tr td { border-top: 1px solid #eef6ef; }

        .test { margin-bottom: 14px; }
        .test-head { background: #eef6ef; border-left: 3px solid #2e8b3e; padding: 5px 8px; }
        .test-head .name { font-size: 11px; font-weight: bold; color: #1f4d27; }
        .test-head .meta { font-size: 8.5px; color: #3d5a43; }

        .results thead th { font-size: 8.5px; text-transform: uppercase; letter-spacing: .6px; color: #3d5a43; text-align: left; padding: 5px 8px; border-bottom: 1px solid #9cc3a4; }
        .results td { padding: 4px 8px; border-bottom: 1px solid #e3eee5; vertical-align: top; }
        .results .section td { padding-top: 8px; font-size: 8.5px; font-weight: bold; color: #2b6b34; text-transform: uppercase; letter-spacing: .8px; border-bottom: none; }
        .results .param { width: 40%; }
        .results .result { width: 18%; color: #0f172a; }
        .results .unit { width: 14%; color: #3d5a43; }
        .results .ref { width: 28%; color: #3d5a43; }
        .method { display: block; font-size: 7.5px; color: #9cc3a4; font-weight: normal; }
        .results .result.flagged { font-weight: bold; }
        .flag-arrow { padding-left: 3px; }
        .longtext { font-weight: normal; white-space: pre-line; }

        .subtable { margin: 2px 0 4px; }
        .subtable th, .subtable td { border: 1px solid #d3e5d7; padding: 3px 6px; text-align: left; font-size: 9px; }
        .subtable th { background: #f3f9f4; color: #3d5a43; font-size: 8px; text-transform: uppercase; letter-spacing: .5px; }

        .notes { margin-top: 6px; padding: 6px 8px; background: #f3f9f4; border: 1px solid #d3e5d7; font-size: 8.5px; color: #334155; }
        .notes p { margin: 0 0 4px; }
        .notes p:last-child { margin-bottom: 0; }
        .notes-table { margin: 4px 0; width: auto; min-width: 60%; }
        .notes-table th, .notes-table td { border: 1px solid #c3d9c8; padding: 2px 8px; text-align: left; font-size: 8.5px; }
        .notes-table th { background: #e3eee5; }

        .clinical { margin: 8px 0; font-size: 9px; }
        .end { text-align: center; font-size: 9px; font-weight: bold; letter-spacing: 2px; color: #3d5a43; margin: 14px 0 26px; }
        .signatures td { text-align: center; vertical-align: bottom; padding: 0 10px; }
        .signature-line { border-top: 1px solid #334155; margin: 34px auto 3px; width: 70%; }
        .signature-name { font-weight: bold; font-size: 9.5px; }
        .signature-title { font-size: 8.5px; color: #5b7a61; }

        .watermark { position: fixed; top: 38%; left: 8%; width: 84%; text-align: center; font-size: 96px; font-weight: bold; color: #ef4444; opacity: 0.08; transform: rotate(-30deg); letter-spacing: 12px; }
        .draft-banner { border: 1px solid #fdba74; background: #fff7ed; color: #9a3412; padding: 6px 8px; margin-bottom: 10px; font-size: 9px; font-weight: bold; text-align: center; }
        .avoid-break { page-break-inside: avoid; }
        tr, .notes, .clinical, .subtable, .patient { page-break-inside: avoid; }
        thead { display: table-header-group; }
        .test-head { page-break-after: avoid; }
    </style>
</head>
<body class="{{ $forPdf ? 'pdf' : 'screen' }} {{ $letterhead ? '' : 'letterhead-off' }}">
<div class="wm lh"><img src="{{ $watermark }}" alt=""></div>

<div class="header lh">
    <div class="lh-top"></div>
    <table class="lh-brand" style="height: 33mm;">
        <tr>
            <td style="width: 150px;"><img src="{{ $logo }}" alt="" style="width: 142px; height: 113px;"></td>
            <td style="width: 44%;">
                <img src="{{ $wordmark }}" alt="Accucare" style="width: 232px; height: 62px;">
                <table class="lh-sub" style="width: 224px;">
                    <tr><td class="ln"><div></div></td><td class="tx">PATHOLOGY LAB</td><td class="ln"><div></div></td></tr>
                </table>
                <div class="lh-tag">Accurate Reports &nbsp;&bull;&nbsp; Better Health</div>
            </td>
            <td>
                <div class="lh-contact">
                    <table>
                        @if ($lab['phone'])<tr><td class="ic"><img src="{{ $icons['phone'] }}" alt=""></td><td style="font-size: 13px;">{{ $lab['phone'] }}</td></tr>@endif
                        @if ($lab['timings'])<tr><td class="ic"><img src="{{ $icons['clock'] }}" alt=""></td><td><small>Lab timing</small>{{ $lab['timings'] }}</td></tr>@endif
                        @if ($lab['home_collection'])<tr><td class="ic"><img src="{{ $icons['home'] }}" alt=""></td><td><small>Home collection</small>{{ $lab['home_collection'] }}</td></tr>@endif
                        @if ($lab['address'])<tr><td class="ic"><img src="{{ $icons['pin'] }}" alt=""></td><td>{{ $lab['address'] }}</td></tr>@endif
                    </table>
                </div>
            </td>
        </tr>
    </table>
    <div class="lh-rule"></div>
</div>

<main>
    @if ($report->revision > 0)
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
            <td class="k">Patient ID</td><td class="v">{{ $report->relationLoaded('patient') && $report->patient ? $report->patient->patient_code : '—' }}</td>
        </tr>
        @if ($report->sample_id)
            <tr>
                <td class="k">Sample ID</td><td class="v">{{ $report->sample_id }}</td>
                <td class="k"></td><td class="v"></td>
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
                            $visible = collect($section['parameters'])->filter(function ($p) use ($item, $isBlank) {
                                if (! ($p['show_in_pdf'] ?? true)) return false;
                                $v = $item->results[$p['key']] ?? null;
                                return ! $isBlank($v);
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
                                $flagged = in_array($flag, ['high', 'low'], true) || $evaluator->isAbnormalQualitative($parameter, $value);
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
                                                    @php
                                                        $rowPositive = collect($row['values'] ?? [])->contains(fn ($v, $k) => $k === 'result' && $evaluator->isAbnormalQualitative(['type' => 'select'], $v));
                                                    @endphp
                                                    <tr>
                                                        <td>{{ $row['label'] }}</td>
                                                        @foreach ($parameter['columns'] as $column)<td @style(['font-weight: bold' => $rowPositive])>{{ $row['values'][$column['key']] ?? '' }}</td>@endforeach
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
                                        {{ $evaluator->formatValue($parameter, $value) ?: '—' }}@if (in_array($flag, ['high', 'low'], true))<span class="flag-arrow">{{ $flag === 'high' ? '↑' : '↓' }}</span>@endif
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

<div class="footer">
    <table>
        <tr>
            <td style="width: 55%;">{{ $lab['report_footer'] }}</td>
            <td style="width: 25%; text-align: center;">{{ $report->report_number }}{{ $report->revision > 0 ? ' · Rev '.$report->revision : '' }}</td>
            <td style="width: 20%; text-align: right;">@unless ($forPdf)Printed {{ $generatedAt->format('d M Y H:i') }}@endunless{{-- PDF page numbers are stamped by ReportPdfService --}}</td>
        </tr>
    </table>
    @if (! empty($lab['footer_quote']))
        <table class="lh lh-quote">
            <tr>
                <td class="qm">&ldquo;</td>
                <td class="qt">{{ $lab['footer_quote'] }}</td>
                <td class="qm">&rdquo;</td>
            </tr>
        </table>
    @endif
</div>

</body>
</html>
