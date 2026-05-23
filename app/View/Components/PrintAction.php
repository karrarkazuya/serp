<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class PrintAction extends Component
{
    public function __construct(
        public string $href,
        public string $label = 'Print',
        public bool $preview = false,
    ) {}

    public function render(): View|Closure|string
    {
        return view('components.print-action');
    }
}
