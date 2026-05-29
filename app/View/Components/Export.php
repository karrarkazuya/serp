<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Export extends Component
{
    /** @var array<int, array{key: string, label: string}> */
    public array $defaultFields;

    /**
     * Map of relation-slug → [child fields] used by the nested-expand UI.
     * Built from config/exportable.php: any field with a `relation` key
     * pulls the target slug's fields list into this map so the Alpine
     * component can render them under an expand toggle.
     *
     * @var array<string, array<int, array{key: string, label: string}>>
     */
    public array $relationFields;

    /**
     * @param  array<int, array{key: string, label: string}>  $fields   All available export columns.
     * @param  array<int, array{key: string, label: string}>  $preset   Pre-selected columns (empty = first 8 available).
     */
    public function __construct(
        public array $fields = [],
        public array $preset = [],
        public string $exportUrl = '',
        public string $modelKey = '',
    ) {
        $this->defaultFields = empty($preset)
            ? array_slice($fields, 0, 8)
            : $preset;

        $this->relationFields = [];
        foreach ($fields as $field) {
            $slug = $field['relation'] ?? null;
            if (!$slug || isset($this->relationFields[$slug])) continue;

            $relConfig = config('exportable')[$slug] ?? null;
            if (!$relConfig || empty($relConfig['fields'])) continue;

            $this->relationFields[$slug] = array_map(
                fn ($f) => ['key' => $f['key'], 'label' => $f['label']],
                $relConfig['fields'],
            );
        }
    }

    public function render(): View|Closure|string
    {
        return view('components.export');
    }
}
