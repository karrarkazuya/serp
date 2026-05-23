<?php

namespace App\Services;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public function download(
        Collection $records,
        array $columns,
        string $format = 'xlsx',
        string $filename = 'export',
        bool $importCompatible = false,
    ): StreamedResponse {
        $filename = preg_replace('/[^a-z0-9_\-]/i', '_', $filename);

        return match ($format) {
            'csv'  => $this->csv($records, $columns, $filename, $importCompatible),
            default => $this->xlsx($records, $columns, $filename, $importCompatible),
        };
    }

    private function xlsx(Collection $records, array $columns, string $filename, bool $importCompatible): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $colCount    = count($columns);

        $colIdx = 1;
        foreach ($columns as $col) {
            $header = $importCompatible ? $col['key'] : $col['label'];
            $sheet->getCellByColumnAndRow($colIdx, 1)->setValue($header);
            $sheet->getColumnDimensionByColumn($colIdx)->setAutoSize(true);
            $colIdx++;
        }

        if ($colCount > 0) {
            $headerRange = 'A1:' . Coordinate::stringFromColumnIndex($colCount) . '1';
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE5D5EF');
        }

        $rowIdx = 2;
        foreach ($records as $record) {
            $colIdx = 1;
            foreach ($columns as $col) {
                $sheet->getCellByColumnAndRow($colIdx, $rowIdx)->setValue($this->value($record, $col));
                $colIdx++;
            }
            $rowIdx++;
        }

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}.xlsx\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    private function csv(Collection $records, array $columns, string $filename, bool $importCompatible): StreamedResponse
    {
        return new StreamedResponse(function () use ($records, $columns, $importCompatible) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            fputcsv($handle, array_map(
                fn ($col) => $importCompatible ? $col['key'] : $col['label'],
                $columns,
            ));

            foreach ($records as $record) {
                fputcsv($handle, array_map(fn ($col) => $this->value($record, $col), $columns));
            }

            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    private function value(mixed $record, array $col): mixed
    {
        $val = is_array($record) ? ($record[$col['key']] ?? null) : ($record->{$col['key']} ?? null);

        if ($val === null) return '';

        if ($val instanceof \DateTimeInterface) return $val->format('Y-m-d H:i:s');

        if (is_bool($val)) return $val ? 'Yes' : 'No';

        return (string) $val;
    }
}
