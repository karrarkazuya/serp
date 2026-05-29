<?php

namespace App\Services;

use App\Services\Company\CompanyContextService;
use Illuminate\Container\Container;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportService
{
    public const MAX_ROWS = 5000;

    private const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    /**
     * Parse an uploaded CSV / XLSX file into an array of associative rows
     * keyed by the file's header row. Headers are returned verbatim — caller
     * normalises them against the import config in processRows().
     *
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>}
     * @throws \RuntimeException when the file is unreadable, empty, or exceeds the row cap.
     */
    public function parse(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path      = $file->getRealPath();

        $reader = match ($extension) {
            'csv'        => $this->csvReader(),
            'xlsx', 'xls' => $this->xlsxReader(),
            default      => throw new \RuntimeException('Unsupported file type. Use CSV or XLSX.'),
        };

        // Read raw values only — we never trigger formula calculation on
        // imported cells (defence against XLSX formula payloads exfiltrating
        // values via external links).
        $reader->setReadDataOnly(true);

        try {
            $spreadsheet = $reader->load($path);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Could not read the file: '.$e->getMessage());
        }

        $sheet      = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();

        if ($highestRow < 1) {
            throw new \RuntimeException('The file is empty.');
        }

        if (($highestRow - 1) > self::MAX_ROWS) {
            throw new \RuntimeException('Too many rows. Max '.self::MAX_ROWS.' allowed per import.');
        }

        $colIndexMax = Coordinate::columnIndexFromString($highestCol);

        $headers = [];
        for ($c = 1; $c <= $colIndexMax; $c++) {
            $val = $sheet->getCell([$c, 1])->getValue();
            $headers[] = is_string($val) ? trim($val) : (string) $val;
        }

        if (count(array_filter($headers, fn ($h) => $h !== '')) === 0) {
            throw new \RuntimeException('No header row detected.');
        }

        $rows = [];
        for ($r = 2; $r <= $highestRow; $r++) {
            $row      = [];
            $hasValue = false;
            for ($c = 1; $c <= $colIndexMax; $c++) {
                $cellValue = $sheet->getCell([$c, $r])->getValue();
                $value     = $this->normalizeCellValue($cellValue);
                $header    = $headers[$c - 1] ?? '';
                if ($header === '') continue;
                $row[$header] = $value;
                if ($value !== '') {
                    $hasValue = true;
                }
            }
            if ($hasValue) {
                $rows[] = $row;
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Process parsed rows against the import config.
     *
     * For each row:
     *  1. Map the file's header strings to field keys (matching by key OR label).
     *  2. Apply per-field type coercion (decimal, date, enum, relation, …).
     *  3. Validate the coerced row against the FormRequest's rules() — same
     *     ruleset the controller's store() would apply.
     *  4. Call the configured service create method.
     *
     * The CALLER must wrap this in DB::transaction so the whole batch rolls
     * back atomically when any row throws.
     *
     * @return array{imported: int}
     * @throws \RuntimeException with a row-specific message on validation / mapping failures.
     */
    public function processRows(array $parsedRows, array $config): array
    {
        $fieldsByKey   = collect($config['fields'])->keyBy('key')->all();
        $headerToKey   = $this->buildHeaderMap($fieldsByKey);

        $service = Container::getInstance()->make($config['service']);
        $method  = $config['service_method'] ?? 'create';

        $requestClass = $config['request'] ?? null;
        if (!$requestClass || !is_subclass_of($requestClass, FormRequest::class)) {
            throw new \RuntimeException('Importable config is missing a valid FormRequest class.');
        }

        $rules         = $this->formRequestRules($requestClass);
        $attributeMap  = $this->buildAttributeMap($fieldsByKey);

        $imported = 0;
        foreach ($parsedRows as $index => $rawRow) {
            $rowNumber = $index + 2; // header on row 1, data starts at row 2

            $normalized = $this->normalizeRow($rawRow, $headerToKey);
            $coerced    = $this->coerceRow($normalized, $fieldsByKey, $config);

            $validator = Validator::make($coerced, $rules);
            $validator->setAttributeNames($attributeMap);
            if ($validator->fails()) {
                $message = $validator->errors()->first();
                throw new \RuntimeException("Row {$rowNumber}: {$message}");
            }

            $validated = $validator->validated();
            $service->{$method}($validated);
            $imported++;
        }

        return ['imported' => $imported];
    }

    /**
     * Build and stream a sample template (XLSX or CSV) the user can fill in
     * and re-upload. The template uses field LABELS so the file is human-
     * readable; the parser accepts either label or key on import.
     */
    public function template(array $config, string $format = 'xlsx'): StreamedResponse
    {
        $filename = preg_replace('/[^a-z0-9_\-]/i', '_', $config['filename'] ?? 'template');
        $fields   = $config['fields'] ?? [];

        return match ($format) {
            'csv'  => $this->templateCsv($filename, $fields),
            default => $this->templateXlsx($filename, $fields),
        };
    }

    // ── internals ───────────────────────────────────────────────────────────

    private function csvReader(): CsvReader
    {
        $reader = new CsvReader();
        $reader->setDelimiter(',');
        $reader->setEnclosure('"');
        $reader->setSheetIndex(0);
        $reader->setInputEncoding('UTF-8');
        return $reader;
    }

    private function xlsxReader(): XlsxReader
    {
        // setReadDataOnly is set by the caller. getValue() returns the raw
        // unevaluated cell content; we never call getCalculatedValue() so any
        // formula text travels through validation as a string and is rejected
        // by the FormRequest unless the field expects it.
        return new XlsxReader();
    }

    private function normalizeCellValue(mixed $value): string
    {
        if ($value === null) return '';

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $str = is_string($value) ? $value : (string) $value;
        $str = trim($str);

        // Strip the leading apostrophe we add to defended CSV / XLSX cells on
        // export. (See ExportService::safeValue / setCellValueExplicit.)
        if ($str !== '' && $str[0] === "'" && strlen($str) > 1 && in_array($str[1], self::FORMULA_TRIGGERS, true)) {
            $str = substr($str, 1);
        }

        return $str;
    }

    /**
     * Build the `field_key => "Human Label"` map that Validator::setAttributeNames
     * uses when interpolating `:attribute` into error messages. The result is
     * the difference between "The name field is required" (key) and "The Name
     * field is required" (label) — the latter being what the rest of the UI
     * uses (Rule 12 spirit).
     *
     * @return array<string, string>
     */
    private function buildAttributeMap(array $fieldsByKey): array
    {
        $map = [];
        foreach ($fieldsByKey as $key => $field) {
            $label = $field['label'] ?? null;
            if (is_string($label) && $label !== '') {
                $map[$key] = $label;
            }
        }
        return $map;
    }

    /**
     * Map each header in the file (label or key) to the canonical field key.
     * Required marker " *" is stripped from labels so the template's
     * generated headers continue to map cleanly.
     *
     * @return array<string, string> lowercased header → field key
     */
    private function buildHeaderMap(array $fieldsByKey): array
    {
        $map = [];
        foreach ($fieldsByKey as $key => $field) {
            $map[strtolower($key)] = $key;
            $label = $field['label'] ?? null;
            if (is_string($label) && $label !== '') {
                $map[strtolower($label)] = $key;
            }
        }
        return $map;
    }

    /**
     * Translate the file row (header → value) into a row keyed by field key.
     * Unknown headers are silently dropped — the FormRequest's rules() act as
     * the whitelist of acceptable input.
     */
    private function normalizeRow(array $rawRow, array $headerToKey): array
    {
        $out = [];
        foreach ($rawRow as $header => $value) {
            $hKey = strtolower(trim(rtrim((string) $header, '*'))); // strip trailing required marker
            $fieldKey = $headerToKey[$hKey] ?? null;
            if ($fieldKey === null) continue;
            $out[$fieldKey] = $value;
        }
        return $out;
    }

    /**
     * Map raw string row values into the typed shape the FormRequest expects.
     * Missing columns fall back to the field default; for company-scoped
     * imports without an explicit company_id, the single active company is
     * used (multi-company users must provide it in the file).
     */
    private function coerceRow(array $rawRow, array $fieldsByKey, array $config): array
    {
        $data             = [];
        $defaultCompanyId = $this->defaultCompanyId($config);

        foreach ($fieldsByKey as $key => $field) {
            $raw = $rawRow[$key] ?? null;
            if ($raw === null || $raw === '') {
                if (array_key_exists('default', $field)) {
                    $raw = $field['default'];
                } elseif ($key === 'company_id' && ($config['company_scoped'] ?? false) && $defaultCompanyId !== null) {
                    $raw = (string) $defaultCompanyId;
                } else {
                    continue;
                }
            }

            $type = $field['type'] ?? 'string';
            $data[$key] = $this->coerceValue($raw, $type, $field);
        }

        return $data;
    }

    private function coerceValue(mixed $raw, string $type, array $field): mixed
    {
        $raw = is_string($raw) ? trim($raw) : $raw;

        return match ($type) {
            'integer'  => $this->coerceInteger($raw),
            'decimal'  => $this->coerceDecimal($raw),
            'boolean'  => $this->coerceBoolean($raw),
            'date'     => $this->coerceDate($raw),
            'datetime' => $this->coerceDateTime($raw),
            'enum'     => $this->coerceEnum($raw, $field['options'] ?? []),
            'array'    => $this->coerceArray($raw, $field['separator'] ?? ';'),
            'relation' => $this->coerceRelation($raw, $field['relation'] ?? []),
            default    => (string) $raw,
        };
    }

    private function coerceInteger(mixed $raw): ?int
    {
        if ($raw === '' || $raw === null) return null;
        return (int) $raw;
    }

    private function coerceDecimal(mixed $raw): ?float
    {
        if ($raw === '' || $raw === null) return null;
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $raw) ?? '';
        return $clean === '' ? null : (float) $clean;
    }

    private function coerceBoolean(mixed $raw): bool
    {
        $val = is_string($raw) ? strtolower(trim($raw)) : $raw;
        return in_array($val, [true, 1, '1', 'true', 'yes', 'y', 'on'], true);
    }

    private function coerceDate(mixed $raw): ?string
    {
        if ($raw === '' || $raw === null) return null;
        try {
            return \Carbon\Carbon::parse((string) $raw)->toDateString();
        } catch (\Throwable) {
            return (string) $raw; // FormRequest validation will reject malformed dates
        }
    }

    private function coerceDateTime(mixed $raw): ?string
    {
        if ($raw === '' || $raw === null) return null;
        try {
            return \Carbon\Carbon::parse((string) $raw)->toDateTimeString();
        } catch (\Throwable) {
            return (string) $raw;
        }
    }

    private function coerceEnum(mixed $raw, array $options): string
    {
        $val = is_string($raw) ? strtolower(trim($raw)) : (string) $raw;
        foreach ($options as $opt) {
            if (strtolower((string) $opt) === $val) {
                return (string) $opt;
            }
        }
        return (string) $raw; // FormRequest will reject unknown values
    }

    private function coerceArray(mixed $raw, string $separator): array
    {
        if (is_array($raw)) return array_values(array_filter($raw, fn ($v) => $v !== null && $v !== ''));

        $parts = array_map('trim', explode($separator, (string) $raw));
        return array_values(array_filter($parts, fn ($v) => $v !== ''));
    }

    /**
     * Accept either a numeric id or a lookup-column value (e.g. "Acme Co"
     * resolved against the companies.name column). Company-scoped tables are
     * filtered by the actor's active companies so an import can never
     * reference another tenant's row by name.
     *
     * Lookup column names are validated against an identifier regex before
     * being interpolated into raw SQL. Although the values come from
     * developer-controlled config (not user input), the regex makes accidental
     * misconfiguration fail loudly instead of becoming a latent SQL-injection
     * surface.
     */
    private function coerceRelation(mixed $raw, array $relation): ?int
    {
        $val = is_string($raw) ? trim($raw) : $raw;
        if ($val === '' || $val === null) return null;

        if (is_numeric($val)) return (int) $val;

        $table  = $relation['table']  ?? null;
        $lookup = $relation['lookup'] ?? ['id', 'name'];

        if (!$table) return null;

        // Drop 'id' and any non-identifier strings BEFORE building the query.
        // If nothing usable remains, the lookup is impossible — return null
        // rather than returning the first row's id from an unfiltered query.
        $usableCols = array_values(array_filter(
            $lookup,
            fn ($col) => is_string($col)
                && strtolower($col) !== 'id'
                && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col) === 1,
        ));
        if (empty($usableCols)) return null;

        $query = DB::table($table);

        if ($this->columnExists($table, 'company_id')) {
            $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
            if (empty($activeCompanyIds)) {
                return null;
            }
            $query->where(function ($q) use ($activeCompanyIds) {
                $q->whereIn('company_id', $activeCompanyIds);
                $q->orWhereNull('company_id');
            });
        }

        $needle = strtolower((string) $val);
        $query->where(function ($q) use ($usableCols, $needle) {
            foreach ($usableCols as $col) {
                $q->orWhereRaw('LOWER('.$col.') = ?', [$needle]);
            }
        });

        $id = $query->value('id');
        return $id ? (int) $id : null;
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    private function defaultCompanyId(array $config): ?int
    {
        if (!($config['company_scoped'] ?? false)) return null;

        $ids = $this->companyContext->getActiveCompanyIds();
        return count($ids) === 1 ? (int) $ids[0] : null;
    }

    /**
     * Borrow the FormRequest's rules() definitions WITHOUT triggering Laravel's
     * standard FormRequest resolution path. Going through the container's
     * `make()` triggers FormRequestServiceProvider's afterResolving callback,
     * which calls authorize() — and in import context the request has no
     * bound user, so authorize() blows up with "hasPermission() on null".
     *
     * Authorization is already handled at the route + controller level; here
     * we ONLY want the rule definitions. Instantiating directly with `new`
     * bypasses the resolution lifecycle while still letting rules() use the
     * `app()` helper to grab any service dependencies it needs (e.g.
     * CompanyContextService for cross-company FK rules).
     */
    private function formRequestRules(string $requestClass): array
    {
        /** @var FormRequest $instance */
        $instance = new $requestClass();

        if (!method_exists($instance, 'rules')) {
            return [];
        }

        return (array) $instance->rules();
    }

    private function templateXlsx(string $filename, array $fields): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        $col = 1;
        foreach ($fields as $field) {
            $label = $field['label'] ?? $field['key'];
            if (!empty($field['required'])) {
                $label .= ' *';
            }
            $sheet->setCellValue([$col, 1], $label);
            $sheet->setCellValueExplicit(
                [$col, 2],
                $this->safeCellValue((string) ($field['example'] ?? '')),
                DataType::TYPE_STRING,
            );
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            $col++;
        }

        $colCount = count($fields);
        if ($colCount > 0) {
            $headerRange = 'A1:'.Coordinate::stringFromColumnIndex($colCount).'1';
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE5D5EF');
        }

        $sheet->setTitle('Import');

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new XlsxWriter($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}.xlsx\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    private function templateCsv(string $filename, array $fields): StreamedResponse
    {
        return new StreamedResponse(function () use ($fields) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($handle, array_map(
                fn ($f) => ($f['label'] ?? $f['key']).(!empty($f['required']) ? ' *' : ''),
                $fields,
            ));
            fputcsv($handle, array_map(
                fn ($f) => $this->safeCellValue((string) ($f['example'] ?? '')),
                $fields,
            ));
            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    private function safeCellValue(string $val): string
    {
        if ($val !== '' && in_array($val[0], self::FORMULA_TRIGGERS, true)) {
            return "'".$val;
        }
        return $val;
    }
}
