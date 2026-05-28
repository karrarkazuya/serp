<?php

namespace App\Services;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * Characters that, when leading a cell value, are interpreted as formulas
     * by Excel, LibreOffice Calc, Numbers, and Google Sheets. Leaving them
     * unescaped lets any user who can write an exported field plant a payload
     * like `=HYPERLINK(...)` or `+cmd|...` that runs in the viewer's spreadsheet.
     * See CWE-1236 (Improper Neutralization of Formula Elements in a CSV File).
     */
    private const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

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
                // Use setValueExplicit + TYPE_STRING so PhpSpreadsheet never parses
                // a leading "=" as a formula. We also prefix formula triggers with
                // a single quote so the cell renders identically when opened in
                // Excel or pasted out as text.
                $sheet->getCellByColumnAndRow($colIdx, $rowIdx)
                    ->setValueExplicit($this->safeValue($record, $col), DataType::TYPE_STRING);
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
                fputcsv($handle, array_map(fn ($col) => $this->safeValue($record, $col), $columns));
            }

            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    private function value(mixed $record, array $col): mixed
    {
        $val = is_array($record) ? ($record[$col['column']] ?? null) : ($record->{$col['column']} ?? null);

        if ($val === null) return '';

        if ($val instanceof \DateTimeInterface) return $val->format('Y-m-d H:i:s');

        if (is_bool($val)) return $val ? 'Yes' : 'No';

        return (string) $val;
    }

    private function safeValue(mixed $record, array $col): string
    {
        $val = (string) $this->value($record, $col);

        if ($val !== '' && in_array($val[0], self::FORMULA_TRIGGERS, true)) {
            return "'" . $val;
        }

        return $val;
    }
}
