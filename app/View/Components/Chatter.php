<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class Chatter extends Component
{
    public Collection $messages;

    public bool $canPostComment;

    public function __construct(
        public ?Model $model = null,
        mixed $messages = null,
        public ?string $commentUrl = null,
        ?bool $canComment = null,
        public string $title = 'Log & Chatter',
        public string $emptyText = 'No activity yet.',
    ) {
        $this->messages = $messages
            ? collect($messages)
            : collect($this->model?->chatterMessages()->with('user')->latest()->get() ?? []);

        $this->canPostComment = (bool) ($canComment ?? false) && filled($this->commentUrl);
    }

    public function render(): View
    {
        return view('components.chatter');
    }
}
