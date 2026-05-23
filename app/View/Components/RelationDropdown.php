<?php

namespace App\View\Components;

use Closure;
use App\Services\Company\CompanyContextService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Component;

class RelationDropdown extends Component
{
    public string $inputName;

    public string $componentId;

    /** @var array<int, array{id:int|string,label:string,color:?string}> */
    public array $selectedOptions = [];

    /** @var array<int, int|string> */
    public array $selectedValues = [];

    /** @var array<int, int|string> */
    public array $exceptValues = [];

    public string $actualTable;

    public bool $canRead = false;

    public bool $canWrite = false;

    public bool $canCreate = false;

    public ?string $searchMoreUrl = null;

    public ?string $createUrl = null;

    public ?string $lookupUrl = null;

    public ?string $colorField = null;

    /**
     * Reusable Odoo-style relation selector.
     *
     * Supported relation modes:
     * - many2one / one2one: single hidden input, e.g. name="company_id"
     * - many2many / one2many: repeated hidden inputs, e.g. name="tags[]"
     *
     * Example:
     * <x-relation-dropdown table="tags" field="name" name="tags" relation="many2many" :selected="$ids" />
     * <x-relation-dropdown table="companies" field="name" name="company_id" relation="many2one" :selected="$companyId" />
     *
     * Tables and display fields are allowlisted below so callers cannot query arbitrary tables/columns.
     *
     * @param  array<int, int|string>|int|string|null  $selected
     */
    public function __construct(
        public string $table,
        public string $field,
        public string $name,
        public ?string $label = null,
        public mixed $selected = null,
        public string $relation = 'many2one',
        public ?bool $multiple = null,
        public mixed $exclude = null,
        public int $limit = 8,
        public bool $compact = false,
        public bool $list = false,
        public ?string $event = null,
        ?string $lookupUrlOverride = null,
    ) {
        $config = config("relation_dropdowns.{$this->table}");
        $user = auth()->user();

        $this->actualTable = $config['table'] ?? $this->table;
        $this->componentId = 'rel_' . md5($this->table . '_' . $this->name . '_' . uniqid('', true));
        $this->multiple = $this->multiple ?? in_array($this->relation, ['many2many', 'one2many'], true);
        $this->inputName = $this->multiple && !str_ends_with($this->name, '[]') ? "{$this->name}[]" : $this->name;
        $this->selectedValues = collect((array) $this->selected)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => is_numeric($value) ? (int) $value : $value)
            ->values()
            ->all();
        $this->exceptValues = collect((array) $this->exclude)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => is_numeric($value) ? (int) $value : $value)
            ->values()
            ->all();

        if (!$config || !$user || !in_array($this->field, $config['fields'], true)) {
            return;
        }

        $this->actualTable = $config['table'] ?? $this->table;
        $open = $config['open'] ?? false;

        $this->canRead   = $open || $user->hasPermission($config['read']);
        $this->canWrite  = $open || $user->hasPermission($config['write']);
        $this->canCreate = $open || $user->hasPermission($config['create_permission'] ?? $config['write']);
        $this->searchMoreUrl = !empty($config['route']) && Route::has($config['route']) ? route($config['route']) : null;
        $this->createUrl = !empty($config['create']) && Route::has($config['create']) ? route($config['create']) : null;
        $this->lookupUrl = $lookupUrlOverride ?? route('relation-dropdown.lookup', ['table' => $this->table]);
        $this->colorField = $config['color'];

        if (!$this->canRead) {
            return;
        }

        $this->selectedOptions = empty($this->selectedValues)
            ? []
            : $this->selectedQuery($config)
            ->whereIn($this->qualifiedValueColumn($config), $this->selectedValues)
            ->when(Schema::hasColumn($this->table, 'company_id'), function ($query) {
                $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();

                empty($activeCompanyIds)
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn($this->table . '.company_id', $activeCompanyIds);
            })
            ->orderBy('label')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'label' => (string) $row->label,
                'color' => $this->colorField ? ($row->{$this->colorField} ?? null) : null,
            ])
            ->values()
            ->all();
    }

    private function qualifiedValueColumn(array $config): string
    {
        return $this->actualTable . '.' . ($config['value_column'] ?? 'id');
    }

    private function selectedQuery(array $config)
    {
        $valueColumn = $this->qualifiedValueColumn($config);
        $labelJoin   = $config['label_join'] ?? null;
        $colorColumn = $this->colorField ? $this->actualTable . '.' . $this->colorField : null;

        $query = DB::table($this->actualTable);

        if (Schema::hasColumn($this->actualTable, 'deleted_at')) {
            $query->whereNull("{$this->actualTable}.deleted_at");
        }

        if ($labelJoin && in_array($this->field, $labelJoin['fields'] ?? [], true)) {
            $joinTable = $labelJoin['table'];
            $query->join($joinTable, "{$this->actualTable}.{$labelJoin['local']}", '=', "{$joinTable}.{$labelJoin['foreign']}")
                ->selectRaw("{$valueColumn} as id, {$joinTable}.{$this->field} as label");
        } else {
            $query->selectRaw("{$valueColumn} as id, {$this->actualTable}.{$this->field} as label");
        }

        if ($colorColumn) {
            $query->addSelect($colorColumn);
        }

        return $query;
    }

    public function render(): View|Closure|string
    {
        return view('components.relation-dropdown');
    }
}
