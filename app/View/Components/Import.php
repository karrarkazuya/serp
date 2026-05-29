<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Import extends Component
{
    public string $sampleXlsxUrl;
    public string $sampleCsvUrl;

    /**
     * @param  string  $modelKey   Key from config/importable.php whose `class` matches the host list's `:model`. Resolved automatically by <x-list>; passing manually is an override.
     * @param  string  $importUrl  POST target for the upload — should always be route('import').
     * @param  string  $label      Optional override for the trigger button text. Defaults to __('common.import').
     */
    public function __construct(
        public string $modelKey,
        public string $importUrl,
        public string $label = '',
    ) {
        $this->sampleXlsxUrl = route('import.template', ['modelKey' => $modelKey, 'format' => 'xlsx']);
        $this->sampleCsvUrl  = route('import.template', ['modelKey' => $modelKey, 'format' => 'csv']);
    }

    public function render(): View|Closure|string
    {
        return view('components.import');
    }
}
