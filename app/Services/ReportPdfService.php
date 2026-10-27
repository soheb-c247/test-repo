<?php

namespace App\Services;

use App\Models\Report;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Renders the pathology report document. The same Blade template produces
 * both the on-screen preview (HTML) and the downloadable PDF, and it reads
 * only the report's stored snapshot — never the live test definitions.
 */
class ReportPdfService
{
    public function __construct(private readonly ResultEvaluator $evaluator) {}

    public function html(Report $report, bool $forPdf = false): string
    {
        $report->loadMissing(['items', 'patient']);

        return view('reports.document', [
            'report' => $report,
            'items' => $report->items,
            'evaluator' => $this->evaluator,
            'lab' => config('lab'),
            'logo' => $this->logoDataUri($forPdf),
            'forPdf' => $forPdf,
            'isDraft' => ! $report->isFinal(),
            'generatedAt' => now(),
        ])->render();
    }

    /**
     * @return string raw PDF bytes
     */
    public function pdf(Report $report): string
    {
        $dompdf = Pdf::loadHTML($this->html($report, forPdf: true))
            ->setPaper('a4', 'portrait')
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isPhpEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'dpi' => 96,
            ])
            ->getDomPDF();

        $dompdf->render();

        // "Page X of Y" needs the final page count, so it is stamped after layout.
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text(
            $canvas->get_width() - 78,
            $canvas->get_height() - 29,
            'Page {PAGE_NUM} of {PAGE_COUNT}',
            $font,
            6.5,
            [0.39, 0.45, 0.55]
        );

        return $dompdf->output();
    }

    public function filename(Report $report): string
    {
        $base = Str::slug($report->report_number ?: 'report-'.$report->id, '-');
        $suffix = $report->isFinal()
            ? ($report->revision > 0 ? '-rev'.$report->revision : '')
            : '-draft';

        return Str::upper($base).$suffix.'.pdf';
    }

    /**
     * The PDF embeds the raster mark (most reliable in dompdf); the HTML
     * preview uses the crisp vector version.
     */
    private function logoDataUri(bool $forPdf): string
    {
        $path = public_path($forPdf ? 'images/accucare-mark.png' : 'images/accucare-mark.svg');
        $mime = $forPdf ? 'image/png' : 'image/svg+xml';
        $data = is_readable($path) ? file_get_contents($path) : '';

        return 'data:'.$mime.';base64,'.base64_encode((string) $data);
    }
}
