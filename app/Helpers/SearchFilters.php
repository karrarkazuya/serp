<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class SearchFilters
{
    private const STRING_OPERATORS = ['contains', 'not_contains', '=', '!=', 'in', 'not_in', 'is_set', 'is_not_set', 'starts_with', 'ends_with'];
    private const NUMBER_OPERATORS = ['=', '!=', '>', '<', '>=', '<=', 'between', 'in', 'not_in', 'is_set', 'is_not_set'];
    private const DATE_OPERATORS = ['=', '!=', '>', '<', '>=', '<=', 'between', 'is_set', 'is_not_set'];
    private const BOOLEAN_OPERATORS = ['=', '!=', 'is_set', 'is_not_set'];
    private const RELATION_OPERATORS = ['=', '!=', 'in', 'not_in', 'is_set', 'is_not_set'];

    public static function apply(Builder $query, Request $request): Builder
    {
        self::applyGlobalSearch($query, $request);
        self::applyAdvancedFilters($query, $request);

        return $query;
    }

    public static function applyGlobalSearch(Builder $query, Request $request): Builder
    {
        $search = trim((string) $request->query('search', ''));

        if ($search === '') {
            return $query;
        }

        $fields = collect(self::fieldsFor($query->getModel()))
            ->filter(fn (array $field) => in_array($field['type'], ['string', 'text', 'email'], true))
            ->all();

        if (empty($fields)) {
            return $query;
        }

        return $query->where(function (Builder $nested) use ($fields, $search) {
            foreach ($fields as $field) {
                $nested->orWhere($field['column'], 'like', "%{$search}%");
            }
        });
    }

    public static function applyAdvancedFilters(Builder $query, Request $request): Builder
    {
        $filters = self::decodeFilters($request);

        if (empty($filters)) {
            return $query;
        }

        $fields = self::fieldsFor($query->getModel());

        foreach ($filters as $filter) {
            $rules = Arr::get($filter, 'rules');

            if (is_array($rules) && !empty($rules)) {
                $match = Arr::get($filter, 'match', 'any') === 'all' ? 'all' : 'any';
                $validRules = array_values(array_filter($rules, fn ($rule) => self::isValidRule($rule, $fields)));

                if (empty($validRules)) {
                    continue;
                }

                $query->where(function (Builder $group) use ($validRules, $fields, $match) {
                    foreach ($validRules as $index => $rule) {
                        $method = $match === 'any' && $index > 0 ? 'orWhere' : 'where';

                        $group->{$method}(function (Builder $nested) use ($rule, $fields) {
                            self::applyRule($nested, $rule, $fields);
                        });
                    }
                });

                continue;
            }

            if (self::isValidRule($filter, $fields)) {
                self::applyRule($query, $filter, $fields);
            }
        }

        return $query;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function fieldsFor(Model|string $model): array
    {
        $instance = is_string($model) ? new $model() : $model;
        $fields = $instance->searchable ?? [];

        $normalized = [];
        foreach ($fields as $key => $field) {
            if (is_string($field)) {
                $field = ['label' => $field];
            }

            $normalized[$key] = array_merge([
                'key' => $key,
                'label' => str($key)->replace('_', ' ')->title()->toString(),
                'column' => $key,
                'type' => 'string',
                'options' => [],
                'relation' => null,
            ], $field);
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function decodedFilters(Request $request): array
    {
        return self::decodeFilters($request);
    }

    /**
     * @return array<int, string>
     */
    public static function operatorsFor(string $type): array
    {
        return match ($type) {
            'integer', 'decimal', 'number' => self::NUMBER_OPERATORS,
            'date', 'datetime' => self::DATE_OPERATORS,
            'boolean' => self::BOOLEAN_OPERATORS,
            'relation' => self::RELATION_OPERATORS,
            default => self::STRING_OPERATORS,
        };
    }

    private static function applyFilter(Builder $query, array $field, string $operator, mixed $value, mixed $valueTo = null): void
    {
        $column = $field['column'];
        $type = $field['type'];

        if ($operator === 'is_set') {
            $query->whereNotNull($column);
            if (in_array($type, ['string', 'text', 'email'], true)) {
                $query->where($column, '!=', '');
            }
            return;
        }

        if ($operator === 'is_not_set') {
            $query->where(function (Builder $nested) use ($column, $type) {
                $nested->whereNull($column);
                if (in_array($type, ['string', 'text', 'email'], true)) {
                    $nested->orWhere($column, '');
                }
            });
            return;
        }

        if (in_array($operator, ['in', 'not_in'], true)) {
            $values = self::valueList($value);
            if (empty($values)) {
                return;
            }

            $operator === 'in'
                ? $query->whereIn($column, $values)
                : $query->whereNotIn($column, $values);

            return;
        }

        if ($operator === 'between') {
            if ($value === null || $value === '' || $valueTo === null || $valueTo === '') {
                return;
            }

            $query->whereBetween($column, [$value, $valueTo]);
            return;
        }

        if ($value === null || $value === '') {
            return;
        }

        if ($type === 'relation' && in_array($operator, ['=', '!='], true) && is_array($value)) {
            $value = $value[0] ?? null;

            if ($value === null || $value === '') {
                return;
            }
        }

        match ($operator) {
            'contains' => $query->where($column, 'like', "%{$value}%"),
            'not_contains' => $query->where($column, 'not like', "%{$value}%"),
            'starts_with' => $query->where($column, 'like', "{$value}%"),
            'ends_with' => $query->where($column, 'like', "%{$value}"),
            '=', '!=', '>', '<', '>=', '<=' => $query->where($column, $operator, $value),
            default => null,
        };
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     */
    private static function isValidRule(mixed $rule, array $fields): bool
    {
        if (!is_array($rule)) {
            return false;
        }

        $key = (string) Arr::get($rule, 'field', '');
        $operator = (string) Arr::get($rule, 'operator', '');

        if (!array_key_exists($key, $fields)) {
            return false;
        }

        return in_array($operator, self::operatorsFor($fields[$key]['type']), true);
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, array<string, mixed>>  $fields
     */
    private static function applyRule(Builder $query, array $rule, array $fields): void
    {
        $field = $fields[(string) Arr::get($rule, 'field')];

        self::applyFilter(
            $query,
            $field,
            (string) Arr::get($rule, 'operator'),
            Arr::get($rule, 'value'),
            Arr::get($rule, 'value_to'),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function decodeFilters(Request $request): array
    {
        $raw = $request->query('filters');

        if (!$raw) {
            return [];
        }

        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * @return array<int, mixed>
     */
    private static function valueList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($item) => $item !== null && $item !== ''));
        }

        return str((string) $value)
            ->explode(',')
            ->map(fn ($item) => trim($item))
            ->filter(fn ($item) => $item !== '')
            ->values()
            ->all();
    }
}
