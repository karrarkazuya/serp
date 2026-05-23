<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GroupsQuery
{
    /**
     * Group an Eloquent collection by a searchable field definition.
     *
     * @param  Collection  $records  Already-fetched collection (with needed relations eager-loaded)
     * @param  array  $fieldDef  Field definition from SearchFilters::fieldsFor()[$key]
     * @return \Illuminate\Support\Collection<int, array{key: string, label: string, count: int, items: Collection}>
     */
    public static function apply(Collection $records, array $fieldDef): \Illuminate\Support\Collection
    {
        $column = $fieldDef['column'];
        $labelFn = self::makeLabelFn($fieldDef);

        return $records
            ->groupBy(fn ($record) => (string) ($record->$column ?? ''))
            ->map(fn ($items, $rawKey) => [
                'key'   => $rawKey,
                'label' => $rawKey !== '' ? $labelFn($rawKey) : '(No Value)',
                'count' => $items->count(),
                'items' => $items,
            ])
            ->sortBy(fn ($g) => $g['key'] === '' ? "\xff" : $g['label'])
            ->values();
    }

    /** @return callable(string): string */
    private static function makeLabelFn(array $fieldDef): callable
    {
        $type = $fieldDef['type'] ?? 'string';

        if ($type === 'boolean') {
            return fn ($v) => in_array($v, ['1', 'true', 'yes'], true) ? 'Yes' : 'No';
        }

        if ($type === 'relation' && !empty($fieldDef['relation']['table'])) {
            $rel = $fieldDef['relation'];
            $labels = DB::table($rel['table'])
                ->pluck($rel['field'], 'id')
                ->mapWithKeys(fn ($name, $id) => [(string) $id => (string) $name])
                ->all();

            return fn ($v) => $labels[$v] ?? '(Unknown)';
        }

        if (!empty($fieldDef['options'])) {
            $map = collect($fieldDef['options'])->pluck('label', 'value')->all();
            return fn ($v) => $map[$v] ?? $v;
        }

        return fn ($v) => (string) $v;
    }
}
