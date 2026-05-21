<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Tree extends Component
{
    public function __construct(
        public array $nodes = [],
        public string $emptyText = 'No records found.',
    ) {}

    public function render()
    {
        return view('components.tree');
    }
}
