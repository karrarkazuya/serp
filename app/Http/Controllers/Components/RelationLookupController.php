<?php

namespace App\Http\Controllers\Components;

use App\Http\Controllers\Controller;
use App\Models\Workflow\WorkflowUser;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RelationLookupController extends Controller
{
    public function __invoke(Request $request, string $table, CompanyContextService $companyContext): JsonResponse
    {
        $config = config("relation_dropdowns.{$table}");
        abort_unless($config, 404);

        // Cap caller-controlled inputs early — these flow into LIKE patterns, paginators,
        // and whereIn() lists. Without a cap a single request can pin DB CPU on big tables.
        // Custom messages so the dropdown UI can surface a human explanation instead of
        // a generic "Lookup failed" — see the JSON 422 handler in relation-dropdown.blade.php.
        $request->validate([
            'search'    => 'sometimes|string|max:100',
            'page'      => 'sometimes|integer|min:1|max:10000',
            'per_page'  => 'sometimes|integer|min:1|max:50',
            'exclude'   => 'sometimes|array|max:500',
            'exclude.*' => 'sometimes|nullable',
        ], [
            'search.max'   => 'Search term is too long (max 100 characters). Shorten it and try again.',
            'exclude.max'  => 'Too many items already selected to filter (max 500). Save the form before adding more.',
            'per_page.max' => 'Page size too large (max 50).',
            'page.max'     => 'Page number too high.',
        ]);

        $field = $request->query('field', 'name');
        abort_unless(in_array($field, $config['fields'] ?? [], true), 404);

        $open = $config['open'] ?? false;
        if (!$open) {
            abort_unless($request->user()?->hasPermission($config['read']), 403);
        }

        // Allow config to map the logical key to a different real DB table
        $table = $config['table'] ?? $table;

        $perPage = max(1, min((int) $request->integer('per_page', 8), 50));
        $exclude = collect((array) $request->query('exclude', []))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => is_numeric($value) ? (int) $value : $value)
            ->values()
            ->all();

        $colorField = $config['color'] ?? null;
        $valueColumn = $table . '.' . ($config['value_column'] ?? 'id');
        $labelJoin = $config['label_join'] ?? null;

        // Use Eloquent when a model is specified (allows scope application)
        $modelClass = $config['model'] ?? null;
        if ($modelClass) {
            $query = $modelClass::query()->from($table);

            if (!empty($config['visible_to_workflow_user'])) {
                $wu = WorkflowUser::where('user_id', auth()->id())->where('active', true)->first();
                if ($wu) {
                    $query->visibleTo($wu);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        } else {
            $query = DB::table($table);
            if (Schema::hasColumn($table, 'deleted_at')) {
                $query->whereNull("{$table}.deleted_at");
            }
        }

        if ($labelJoin && in_array($field, $labelJoin['fields'] ?? [], true)) {
            $joinTable = $labelJoin['table'];
            $query->join($joinTable, "{$table}.{$labelJoin['local']}", '=', "{$joinTable}.{$labelJoin['foreign']}")
                ->selectRaw("{$valueColumn} as id, {$joinTable}.{$field} as label");
            $searchColumn = "{$joinTable}.{$field}";
        } else {
            $query->selectRaw("{$valueColumn} as id, {$table}.{$field} as label");
            $searchColumn = "{$table}.{$field}";
        }

        if ($colorField) {
            $query->addSelect("{$table}.{$colorField}");
        }

        if (!empty($config['active_only']) && Schema::hasColumn($table, 'active')) {
            $query->where("{$table}.active", true);
        }

        if (!empty($config['where'])) {
            foreach ($config['where'] as [$column, $operator, $value]) {
                $query->where("{$table}.{$column}", $operator, $value);
            }
        }

        if (!empty($exclude)) {
            $query->whereNotIn($valueColumn, $exclude);
        }

        if ($search = $request->query('search')) {
            $query->where($searchColumn, 'like', "%{$search}%");
        }

        if (Schema::hasColumn($table, 'company_id')) {
            $activeCompanyIds = $companyContext->getActiveCompanyIds();
            empty($activeCompanyIds)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn("{$table}.company_id", $activeCompanyIds);
        }

        $records = $query->orderBy($searchColumn)->paginate($perPage);

        $records->getCollection()->transform(fn ($row) => [
            'id' => $row->id,
            'label' => (string) $row->label,
            'color' => $colorField ? ($row->{$colorField} ?? null) : null,
        ]);

        return response()->json($records);
    }
}
