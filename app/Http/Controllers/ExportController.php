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
        $modelKey = (string) $request->input('model', '');
        $config   = config('exportable')[$modelKey] ?? null;

        abort_unless($config !== null, 404, 'Unknown export model.');

        $user = $request->user();
        abort_unless($user->hasPermission($config['permission']), 403);

        $allowedKeys = array_column($config['fields'], 'key');
        $requestedKeys = array_values(array_intersect(
            (array) $request->input('fields', $allowedKeys),
            $allowedKeys,
        ));

        abort_if(empty($requestedKeys), 422, 'No fields selected for export.');

        $columns = array_values(array_filter(
            $config['fields'],
            fn ($f) => in_array($f['key'], $requestedKeys, true),
        ));

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
        );
    }
}
