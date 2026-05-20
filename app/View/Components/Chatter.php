<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Chatter extends Component
{
    public string $apiUrl;
    public string $postUrl;
    public string $fileBaseUrl;

    public function __construct(
        public string $modelType,
        public int $modelId,
        public bool $canComment = false,
    ) {
        $this->apiUrl      = route('api.chatter.index');
        $this->postUrl     = route('api.chatter.store');
        $this->fileBaseUrl = url('chatter');
    }

    public function render(): View
    {
        return view('components.chatter');
    }
}
