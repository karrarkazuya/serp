<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Component;

class TableList extends Component
{
    public bool $isEmpty;
    public bool $hasPagination;
    public bool $canExport;
    public bool $canDelete;
    public bool $canImport;
    public ?string $importModelKey;

    /**
     * @param  ?string  $model         Model class string — auto-derives canExport / canDelete / canImport from policy.
     * @param  ?bool    $canExport     Explicit override; null = auto-derive from $model.
     * @param  ?bool    $canDelete     Explicit override; null = auto-derive from $model.
     * @param  ?bool    $canImport     Explicit override; null = auto-derive from $model.
     * @param  string   $bulkDeleteUrl POST target for bulk delete; Delete button is disabled when empty.
     */
    public function __construct(
        public mixed $paginator = null,
        public string $emptyText = 'No records found.',
        public string $class = '',
        public bool $selectable = false,
        public int $totalCount = 0,
        public bool $grouped = false,
        public ?string $model = null,
        ?bool $canExport = null,
        ?bool $canDelete = null,
        ?bool $canImport = null,
        public string $bulkDeleteUrl = '',
    ) {
        $this->canExport = $canExport ?? $this->gate('export');
        $this->canDelete = $canDelete ?? $this->gate('delete');
        $this->importModelKey = $this->resolveImportModelKey();
        $this->canImport = ($canImport ?? $this->gate('import')) && $this->importModelKey !== null;

        if ($this->grouped) {
            $this->isEmpty       = false;
            $this->hasPagination = false;
        } else {
            $this->isEmpty = $paginator !== null
                && method_exists($paginator, 'isEmpty')
                && $paginator->isEmpty();

            $this->hasPagination = $paginator instanceof LengthAwarePaginator
                && $paginator->hasPages();
        }
    }

    public function render(): View|Closure|string
    {
        return view('components.list');
    }

    private function gate(string $ability): bool
    {
        if (!$this->model || !auth()->check()) {
            return false;
        }

        return rescue(fn () => Gate::allows($ability, $this->model), false, false);
    }

    /**
     * Find the config/importable.php key whose `class` matches the list's
     * `:model`. Returns null when the model has no importable entry —
     * the Import trigger is hidden in that case even if the Gate would allow.
     */
    private function resolveImportModelKey(): ?string
    {
        if (!$this->model) {
            return null;
        }

        foreach ((array) config('importable', []) as $key => $config) {
            if (($config['class'] ?? null) === $this->model) {
                return (string) $key;
            }
        }

        return null;
    }
}
