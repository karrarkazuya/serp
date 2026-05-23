<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class TableList extends Component
{
    public bool $isEmpty;

    public bool $hasPagination;

    public function __construct(
        public mixed $paginator = null,
        public string $emptyText = 'No records found.',
        public string $class = '',
        public bool $selectable = false,
        public int $totalCount = 0,
        public bool $grouped = false,
    ) {
        if ($this->grouped) {
            $this->isEmpty      = false;
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
}
