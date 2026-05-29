<?php

namespace App\Services;

use App\Helpers\SearchFilters;
use Illuminate\Database\Eloquent\SoftDeletes;
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
        ?string $modelClass = null,
    ): StreamedResponse {
        $filename = preg_replace('/[^a-z0-9_\-]/i', '_', $filename);

        return match ($format) {
            'csv'  => $this->csv($records, $columns, $filename, $importCompatible, $modelClass),
            default => $this->xlsx($records, $columns, $filename, $importCompatible, $modelClass),
        };
    }

    private function xlsx(Collection $records, array $columns, string $filename, bool $importCompatible, ?string $modelClass = null): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $colCount    = count($columns);
        $relMaps     = $this->buildRelationMaps($records, $columns);
        $optionMaps  = $this->buildOptionMaps($modelClass, $columns);

        $colIdx = 1;
        foreach ($columns as $col) {
            $header = $importCompatible ? $col['key'] : $col['label'];
            $sheet->setCellValue([$colIdx, 1], $header);
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
                // Use setCellValueExplicit + TYPE_STRING so PhpSpreadsheet never parses
                // a leading "=" as a formula. We also prefix formula triggers with
                // a single quote so the cell renders identically when opened in
                // Excel or pasted out as text.
                $sheet->setCellValueExplicit(
                    [$colIdx, $rowIdx],
                    $this->safeValue($record, $col, $relMaps, $optionMaps),
                    DataType::TYPE_STRING,
                );
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

    private function csv(Collection $records, array $columns, string $filename, bool $importCompatible, ?string $modelClass = null): StreamedResponse
    {
        return new StreamedResponse(function () use ($records, $columns, $importCompatible, $modelClass) {
            $handle     = fopen('php://output', 'w');
            $relMaps    = $this->buildRelationMaps($records, $columns);
            $optionMaps = $this->buildOptionMaps($modelClass, $columns);
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            fputcsv($handle, array_map(
                fn ($col) => $importCompatible ? $col['key'] : $col['label'],
                $columns,
            ));

            foreach ($records as $record) {
                fputcsv($handle, array_map(fn ($col) => $this->safeValue($record, $col, $relMaps, $optionMaps), $columns));
            }

            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    /**
     * Pre-load related records for every nested-relation column in a single
     * query per (slug, fk) pair. Avoids requiring `creator()`/`updater()`
     * relation methods on every Eloquent model — we just bulk-fetch by id.
     *
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<string, array<int, mixed>>  Keyed by "{slug}:{fk_column}" → id-keyed map of related records.
     */
    private function buildRelationMaps(Collection $records, array $columns): array
    {
        $maps = [];

        foreach ($columns as $col) {
            if (empty($col['relation_slug'])) continue;

            $mapKey = $col['relation_slug'] . ':' . $col['column'];
            if (isset($maps[$mapKey])) continue;

            $relConfig = config('exportable')[$col['relation_slug']] ?? null;
            if (!$relConfig || empty($relConfig['class'])) {
                $maps[$mapKey] = [];
                continue;
            }

            $ids = $records
                ->map(fn ($r) => is_array($r) ? ($r[$col['column']] ?? null) : ($r->{$col['column']} ?? null))
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->unique()
                ->values()
                ->all();

            if (empty($ids)) {
                $maps[$mapKey] = [];
                continue;
            }

            $modelClass = $relConfig['class'];
            $query      = $modelClass::query();
            if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
                $query->withTrashed();
            }

            $maps[$mapKey] = $query->whereIn('id', $ids)->get()->keyBy('id')->all();
        }

        return $maps;
    }

    /**
     * Build a `[column_name => [raw_value => human_label]]` map by reading
     * the model's `$searchable` declarations. Any field declared with options
     * (or `'type' => 'select'`) becomes a lookup table so the export emits
     * the human label ("Current") instead of the raw enum ("current") —
     * matching what the user sees in the filter dropdown and on detail pages.
     *
     * Skips nested-relation columns (handled by buildRelationMaps), and skips
     * columns that don't carry options in $searchable.
     *
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<string, array<string, string>>
     */
    private function buildOptionMaps(?string $modelClass, array $columns): array
    {
        if (!$modelClass || !class_exists($modelClass)) {
            return [];
        }

        try {
            $searchable = SearchFilters::fieldsFor($modelClass);
        } catch (\Throwable $e) {
            return [];
        }

        $maps = [];
        foreach ($columns as $col) {
            if (!empty($col['relation_slug'])) continue;

            $searchField = $searchable[$col['key']] ?? null;
            if (!$searchField || empty($searchField['options'])) continue;

            $valueToLabel = [];
            foreach ($searchField['options'] as $opt) {
                if (!is_array($opt) || !array_key_exists('value', $opt)) continue;
                $valueToLabel[(string) $opt['value']] = (string) ($opt['label'] ?? $opt['value']);
            }
            if ($valueToLabel) {
                $maps[$col['column']] = $valueToLabel;
            }
        }

        return $maps;
    }

    /**
     * @param  array<string, array<int, mixed>>  $relMaps
     * @param  array<string, array<string, string>>  $optionMaps
     */
    private function value(mixed $record, array $col, array $relMaps = [], array $optionMaps = []): mixed
    {
        if (!empty($col['relation_slug'])) {
            $fk = is_array($record) ? ($record[$col['column']] ?? null) : ($record->{$col['column']} ?? null);
            if ($fk === null || $fk === '') return '';

            $mapKey  = $col['relation_slug'] . ':' . $col['column'];
            $related = $relMaps[$mapKey][$fk] ?? null;
            if ($related === null) return '';

            $childCol = $col['child_column'] ?? null;
            if ($childCol === null) return '';

            $val = is_array($related) ? ($related[$childCol] ?? null) : ($related->{$childCol} ?? null);
        } else {
            $val = is_array($record) ? ($record[$col['column']] ?? null) : ($record->{$col['column']} ?? null);

            // Map enum-like raw values to their declared labels.
            if ($val !== null && $val !== '' && isset($optionMaps[$col['column']])) {
                $key = $val instanceof \BackedEnum ? (string) $val->value : (string) $val;
                if (isset($optionMaps[$col['column']][$key])) {
                    return $optionMaps[$col['column']][$key];
                }
            }
        }

        if ($val === null) return '';

        if ($val instanceof \DateTimeInterface) return $val->format('Y-m-d H:i:s');

        if (is_bool($val)) return $val ? 'Yes' : 'No';

        return (string) $val;
    }

    /**
     * @param  array<string, array<int, mixed>>  $relMaps
     * @param  array<string, array<string, string>>  $optionMaps
     */
    private function safeValue(mixed $record, array $col, array $relMaps = [], array $optionMaps = []): string
    {
        $val = (string) $this->value($record, $col, $relMaps, $optionMaps);

        if ($val !== '' && in_array($val[0], self::FORMULA_TRIGGERS, true)) {
            return "'" . $val;
        }

        return $val;
    }
}
