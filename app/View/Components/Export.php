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
    }

    public function render(): View|Closure|string
    {
        return view('components.export');
    }
}
