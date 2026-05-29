<?php

namespace App\Http\Controllers;

use App\Services\Company\CompanyContextService;
use App\Services\ExportService;
use App\Helpers\SearchFilters;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        private readonly ExportService $exportService,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function export(Request $request): StreamedResponse
    {
        $modelKey   = (string) $request->input('model', '');
        $exportable = (array) config('exportable', []);
        $config     = $exportable[$modelKey] ?? null;

        abort_unless($config !== null, 404, 'Unknown export model.');

        $user = $request->user();
        abort_unless($user->hasPermission($config['permission']), 403);

        $fieldsByKey = collect($config['fields'])->keyBy('key')->all();
        $requested   = (array) $request->input('fields', array_keys($fieldsByKey));

        $columns = $this->resolveColumns($requested, $fieldsByKey, $exportable);
        abort_if(empty($columns), 422, 'No fields selected for export.');

        $dbColumns = array_unique(array_merge(['id'], array_column($columns, 'column')));

        $model = app($config['class']);
        $query = $model->newQuery()->select($dbColumns);

        if ($config['company_scoped'] ?? false) {
            $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
            abort_if(empty($activeCompanyIds), 403);
            $query->whereIn('company_id', $activeCompanyIds);
        }

        if ($request->boolean('select_all')) {
            $extra = [];
            if ($request->filled('query_string')) {
                parse_str((string) $request->input('query_string'), $extra);
                $fakeRequest = Request::create('', 'GET', $extra);
                SearchFilters::apply($query, $fakeRequest);
            }
            // Mirror the active/archived scope that module controllers apply
            $filterParam = $extra['filter'] ?? null;
            if (method_exists($model, 'scopeActive')) {
                if ($filterParam === 'archived') {
                    if (method_exists($model, 'scopeInactive')) {
                        $query->inactive();
                    }
                } elseif ($filterParam !== 'all') {
                    $query->active();
                }
            }

            // Apply module-specific extra URL params declared in config (e.g. state, journal_id)
            foreach ($config['extra_params'] ?? [] as $param => $column) {
                $value = $extra[$param] ?? null;
                if ($value !== null && $value !== '') {
                    $query->where($column, $value);
                }
            }
        } else {
            $ids = array_values(array_filter(
                array_map('intval', (array) $request->input('ids', [])),
                fn ($id) => $id > 0,
            ));
            abort_if(empty($ids), 422, 'No records selected.');
            $query->whereIn('id', $ids);
        }

        $records = $query->get();

        return $this->exportService->download(
            $records,
            $columns,
            (string) $request->input('format', 'xlsx'),
            (string) ($config['filename'] ?? $modelKey),
            $request->boolean('import_compatible'),
            (string) $config['class'],
        );
    }

    /**
     * Resolve incoming field keys against the model's exportable config.
     * Accepts both flat keys (`name`) and path-style nested keys (`created_by/email`).
     * Unknown keys are silently dropped — the controller never exposes columns
     * absent from config/exportable.php (whitelist contract).
     *
     * For a top-level field that has a `relation` set, the column auto-resolves
     * to the relation's `name` field so the user sees a name instead of a raw FK id.
     *
     * @param  array<int, mixed>  $requested
     * @param  array<string, array<string, mixed>>  $fieldsByKey
     * @param  array<string, array<string, mixed>>  $exportable
     * @return array<int, array<string, mixed>>
     */
    private function resolveColumns(array $requested, array $fieldsByKey, array $exportable): array
    {
        $columns = [];

        foreach ($requested as $key) {
            if (!is_string($key) || $key === '') continue;

            if (str_contains($key, '/')) {
                $resolved = $this->resolveNested($key, $fieldsByKey, $exportable);
                if ($resolved) $columns[] = $resolved;
                continue;
            }

            $field = $fieldsByKey[$key] ?? null;
            if (!$field) continue;

            if (!empty($field['relation'])) {
                $resolved = $this->resolveTopLevelRelation($field, $exportable);
                if ($resolved) {
                    $columns[] = $resolved;
                    continue;
                }
            }

            $columns[] = $field;
        }

        return $columns;
    }

    /**
     * @param  array<string, array<string, mixed>>  $fieldsByKey
     * @param  array<string, array<string, mixed>>  $exportable
     * @return array<string, mixed>|null
     */
    private function resolveNested(string $key, array $fieldsByKey, array $exportable): ?array
    {
        [$parentKey, $childKey] = explode('/', $key, 2);

        $parentField = $fieldsByKey[$parentKey] ?? null;
        if (!$parentField || empty($parentField['relation'])) return null;

        $relConfig = $exportable[$parentField['relation']] ?? null;
        if (!$relConfig) return null;

        $childFields = collect($relConfig['fields'])->keyBy('key')->all();
        $childField  = $childFields[$childKey] ?? null;
        if (!$childField) return null;

        return [
            'key'           => $key,
            'label'         => $parentField['label'] . ' / ' . $childField['label'],
            'column'        => $parentField['column'],
            'relation_slug' => $parentField['relation'],
            'child_column'  => $childField['column'],
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, array<string, mixed>>  $exportable
     * @return array<string, mixed>|null
     */
    private function resolveTopLevelRelation(array $field, array $exportable): ?array
    {
        $relConfig = $exportable[$field['relation']] ?? null;
        if (!$relConfig) return null;

        $defaultKey  = $field['relation_default'] ?? 'name';
        $childFields = collect($relConfig['fields'])->keyBy('key')->all();
        $childField  = $childFields[$defaultKey] ?? null;
        if (!$childField) return null;

        return [
            'key'           => $field['key'],
            'label'         => $field['label'],
            'column'        => $field['column'],
            'relation_slug' => $field['relation'],
            'child_column'  => $childField['column'],
        ];
    }
}
