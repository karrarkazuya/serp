<?php

namespace App\View\Components;

use App\Helpers\SearchFilters;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

class Search extends Component
{
    /** @var array<string, array<string, mixed>> */
    public array $fields;

    /** @var array<int, array<string, mixed>> */
    public array $activeFilters;

    /** @var array<int, array<string, string|null>> */
    public array $groupOptions = [];

    /** @var array<int, array<string, mixed>> */
    public array $quickOptions = [];

    /** @var array<int, array<string, mixed>> */
    public array $activeQuickFilters = [];

    public ?string $activeGroupBy;

    public ?string $activeGroupLabel = null;

    /** @var array<string, string> */
    public array $operatorLabels = [
        '=' => '=',
        '!=' => '!=',
        'contains' => 'contains',
        'not_contains' => 'does not contain',
        'in' => 'is in',
        'not_in' => 'is not in',
        'is_set' => 'is set',
        'is_not_set' => 'is not set',
        'starts_with' => 'starts with',
        'ends_with' => 'ends with',
        '>' => '>',
        '<' => '<',
        '>=' => '>=',
        '<=' => '<=',
        'between' => 'between',
    ];

    /**
     * @param  class-string  $model
     * @param  array<string, mixed>  $preserve
     * @param  array<int, array<string, string>>  $quickFilters
     * @param  array<int, array<string, string>>  $groupBy
     */
    public function __construct(
        public string $model,
        public ?string $action = null,
        public string $placeholder = 'Search...',
        public array $preserve = [],
        public array $quickFilters = [],
        public array $groupBy = [],
    ) {
        $this->fields = SearchFilters::fieldsFor($this->model);
        $this->activeFilters = SearchFilters::decodedFilters(request());
        $this->action ??= request()->url();
        $this->activeGroupBy = request('group_by');

        foreach ($this->fields as $key => $field) {
            $this->fields[$key]['operators'] = SearchFilters::operatorsFor($field['type']);

            if (!empty($field['relation']['table'])) {
                $this->fields[$key]['relation']['lookup_url'] = Route::has('relation-dropdown.lookup')
                    ? route('relation-dropdown.lookup', ['table' => $field['relation']['table']])
                    : null;
            }
        }

        foreach ($this->quickFilters as $filter) {
            $params = $filter['params'] ?? $this->paramsFromUrl($filter['url'] ?? '');
            $option = [
                'label' => $filter['label'] ?? '',
                'url' => $filter['url'] ?? '#',
                'params' => $params,
                'clear_url' => $this->clearUrlForParams(array_keys($params), $filter['clear_params'] ?? []),
            ];

            if (!array_key_exists('filters', $params) && !empty($params) && $this->paramsAreActive($params)) {
                $this->activeQuickFilters[] = $option;
            }

            $this->quickOptions[] = $option;
        }

        foreach ($this->groupBy as $group) {
            $key = $group['key'] ?? $this->groupKeyFromUrl($group['url'] ?? '');
            $option = [
                'key' => $key,
                'label' => $group['label'] ?? (string) $key,
                'url' => $group['url'] ?? '#',
            ];

            if ($key && $key === $this->activeGroupBy) {
                $this->activeGroupLabel = $option['label'];
            }

            $this->groupOptions[] = $option;
        }
    }

    public function render(): View|Closure|string
    {
        return view('components.search');
    }

    private function groupKeyFromUrl(string $url): ?string
    {
        $params = $this->paramsFromUrl($url);

        return isset($params['group_by']) ? (string) $params['group_by'] : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function paramsFromUrl(string $url): array
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if (!$query) {
            return [];
        }

        parse_str($query, $params);

        return $params;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function paramsAreActive(array $params): bool
    {
        foreach ($params as $key => $value) {
            $current = request()->query($key);

            if ((string) ($current ?? '') !== (string) ($value ?? '')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string|int>  $keys
     */
    private function clearUrlForParams(array $keys, array $replacements = []): string
    {
        $params = request()->query();

        foreach ($keys as $key) {
            unset($params[$key]);
        }

        foreach ($replacements as $key => $value) {
            if ($value === null) {
                unset($params[$key]);
                continue;
            }

            $params[$key] = $value;
        }

        unset($params['page']);

        $query = http_build_query($params);

        return $this->action . ($query ? "?{$query}" : '');
    }
}
