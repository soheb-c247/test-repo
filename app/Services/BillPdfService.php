<?php

namespace App\Services;

use App\Models\Bill;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Renders the bill / payment receipt. The same Blade template produces the
 * printable HTML view and the PDF. Receipts never include test results.
 */
class BillPdfService
{
    public function html(Bill $bill, bool $forPdf = false): string
    {
        $bill->loadMissing(['items', 'payments', 'report', 'patient']);

        return view('billing.receipt', [
            'bill' => $bill,
            'lab' => config('lab'),
            'currency' => config('lab.currency'),
            'logo' => $this->logoDataUri($forPdf),
            'forPdf' => $forPdf,
            'generatedAt' => now(),
        ])->render();
    }

    /**
     * @return string raw PDF bytes
     */
    public function pdf(Bill $bill): string
    {
        return Pdf::loadHTML($this->html($bill, forPdf: true))
            ->setPaper('a4', 'portrait')
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isPhpEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'dpi' => 96,
            ])
            ->output();
    }

    public function filename(Bill $bill): string
    {
        return Str::upper(Str::slug($bill->bill_number ?: 'bill-'.$bill->id, '-')).($bill->isCancelled() ? '-cancelled' : '').'.pdf';
    }

    private function logoDataUri(bool $forPdf): string
    {
        $path = public_path($forPdf ? 'images/accucare-mark.png' : 'images/accucare-mark.svg');
        $mime = $forPdf ? 'image/png' : 'image/svg+xml';
        $data = is_readable($path) ? file_get_contents($path) : '';

        return 'data:'.$mime.';base64,'.base64_encode((string) $data);
    }
}
