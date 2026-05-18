<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SortsTable
{
    public static function apply(Builder $query, Request $request, ?string $defaultColumn = null, string $defaultDirection = 'asc'): Builder
    {
        $sort = self::resolve($query->getModel(), $request, $defaultColumn, $defaultDirection);

        return $query->orderBy($sort['column'], $sort['direction']);
    }

    /**
     * @return array{sort: string, column: string, direction: 'asc'|'desc'}
     */
    public static function resolve(Model|string $model, Request $request, ?string $defaultColumn = null, string $defaultDirection = 'asc'): array
    {
        $columns = self::columnsFor($model);
        $defaultColumn ??= array_key_first($columns);

        $sort = (string) $request->query('sort', $defaultColumn);
        $direction = strtolower((string) $request->query('direction', $defaultDirection)) === 'desc' ? 'desc' : 'asc';

        if (!array_key_exists($sort, $columns)) {
            $sort = $defaultColumn;
        }

        return [
            'sort' => $sort,
            'column' => $columns[$sort],
            'direction' => $direction,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function columnsFor(Model|string $model): array
    {
        $instance = is_string($model) ? new $model() : $model;
        $columns = $instance->sortable ?? [];

        if (empty($columns)) {
            throw new \InvalidArgumentException(sprintf('%s does not define sortable columns.', $instance::class));
        }

        return $columns;
    }
}
