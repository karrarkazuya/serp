<?php

namespace App\Http\Controllers\Components;

use App\Http\Controllers\Controller;
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

        $columns = ['id', $field];
        $colorField = $config['color'] ?? null;
        if ($colorField) {
            $columns[] = $colorField;
        }

        $query = DB::table($table)->select($columns);

        if (!empty($exclude)) {
            $query->whereNotIn('id', $exclude);
        }

        if ($search = $request->query('search')) {
            $query->where($field, 'like', "%{$search}%");
        }

        if (Schema::hasColumn($table, 'company_id')) {
            $activeCompanyIds = $companyContext->getActiveCompanyIds();
            empty($activeCompanyIds)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('company_id', $activeCompanyIds);
        }

        $records = $query->orderBy($field)->paginate($perPage);

        $records->getCollection()->transform(fn ($row) => [
            'id' => $row->id,
            'label' => (string) $row->{$field},
            'color' => $colorField ? ($row->{$colorField} ?? null) : null,
        ]);

        return response()->json($records);
    }
}
