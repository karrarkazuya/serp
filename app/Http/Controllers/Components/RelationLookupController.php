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

        $field = $request->query('field', 'name');
        abort_unless(in_array($field, $config['fields'] ?? [], true), 404);
        abort_unless($request->user()?->hasPermission($config['read']), 403);

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
