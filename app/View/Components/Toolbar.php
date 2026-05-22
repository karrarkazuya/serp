<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Toolbar extends Component
{
    public bool $isRtl;

    public function __construct(
        public ?string $newHref = null,
        public ?string $prevHref = null,
        public ?string $nextHref = null,
        public ?int $position = null,
        public ?int $total = null,
    ) {
        $this->isRtl = app()->getLocale() === 'ar';
    }

    public function render(): View|Closure|string
    {
        return view('components.toolbar');
    }
}
