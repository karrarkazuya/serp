<?php

namespace App\Services\Accounting;

use App\Services\ExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Wraps the generic ExportService with PDF support and report-specific
 * dispatch logic. PDF uses DomPDF rendering a Blade template;
 * XLSX & CSV use PhpSpreadsheet via the existing ExportService.
 */
class AccountingReportExportService
{
    public function __construct(
        private readonly ExportService $exporter,
    ) {}

    /**
     * Stream a tabular report.
     *
     * @param  Collection<int, array|object>  $records  Iterable of rows.
     * @param  array<int, array{key:string,label:string}>  $columns
     * @param  string  $format  xlsx|csv|pdf
     * @param  string  $filename  base filename (no extension)
     * @param  string  $title  human title shown in PDF header
     * @param  array<string, string>  $meta  display-only header lines (date range, partner, etc.)
     * @param  array<int, array{key:string,label:string,value:string}>  $totals  optional totals row
     */
    public function download(
        Collection $records,
        array $columns,
        string $format,
        string $filename,
        string $title,
        array $meta = [],
        array $totals = [],
    ): Response|StreamedResponse {
        $filename = preg_replace('/[^a-z0-9_\-]/i', '_', $filename);

        if ($format === 'pdf') {
            return $this->pdf($records, $columns, $filename, $title, $meta, $totals);
        }

        return $this->exporter->download($records, $columns, $format, $filename);
    }

    private function pdf(
        Collection $records,
        array $columns,
        string $filename,
        string $title,
        array $meta,
        array $totals,
    ): Response {
        $pdf = Pdf::loadView('accounting.reports.export-pdf', [
            'title'    => $title,
            'meta'     => $meta,
            'columns'  => $columns,
            'records'  => $records,
            'totals'   => $totals,
            'company'  => optional(auth()->user())->company?->name,
            'printed_at' => now()->format('Y-m-d H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("{$filename}.pdf");
    }
}
