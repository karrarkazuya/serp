<div class="bg-white border-t border-gray-200" x-data="{ tab: 'all' }">

    {{-- Chatter header --}}
    <div class="flex items-center gap-4 px-6 pt-4 pb-0 border-b border-gray-100">
        <span class="text-sm font-semibold text-gray-700">{{ $title }}</span>
        <div class="flex gap-1 ms-auto">
            <button @click="tab = 'all'"
                    :class="tab === 'all' ? 'bg-purple-100 text-purple-700' : 'text-gray-500 hover:bg-gray-100'"
                    class="px-2.5 py-1 rounded text-xs font-medium transition-colors">{{ __('common.all') }}</button>
            <button @click="tab = 'comment'"
                    :class="tab === 'comment' ? 'bg-purple-100 text-purple-700' : 'text-gray-500 hover:bg-gray-100'"
                    class="px-2.5 py-1 rounded text-xs font-medium transition-colors">{{ __('common.comments') }}</button>
            <button @click="tab = 'log'"
                    :class="tab === 'log' ? 'bg-purple-100 text-purple-700' : 'text-gray-500 hover:bg-gray-100'"
                    class="px-2.5 py-1 rounded text-xs font-medium transition-colors">{{ __('common.activity_tab') }}</button>
        </div>
    </div>

    {{-- Add comment --}}
    @if($canPostComment)
    <div class="px-6 py-4 border-b border-gray-100">
        <form action="{{ $commentUrl }}" method="POST">
            @csrf
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-full bg-purple-600 flex items-center justify-center text-xs font-bold text-white shrink-0">
                    {{ auth()->user()->initials }}
                </div>
                <div class="flex-1">
                    <textarea name="body" rows="2" placeholder="{{ __('common.write_comment') }}"
                              class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 resize-none transition-shadow"
                              required></textarea>
                    <div class="flex justify-end mt-1.5">
                        <button type="submit"
                                class="px-3 py-1.5 bg-purple-600 text-white text-xs font-medium rounded-md hover:bg-purple-700 transition-colors">
                            {{ __('common.send') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    @endif

    {{-- Messages --}}
    <div class="px-6 py-4 space-y-4 max-h-96 overflow-y-auto">
        @forelse($messages as $message)
            <div class="chatter-message {{ $message->message_type }} flex gap-3"
                 x-show="tab === 'all' || tab === '{{ $message->message_type }}'">

                {{-- Avatar --}}
                <div class="shrink-0 mt-0.5">
                    @if($message->user)
                        <div class="w-7 h-7 rounded-full {{ $message->isComment() ? 'bg-purple-600' : 'bg-gray-200' }} flex items-center justify-center text-xs font-bold {{ $message->isComment() ? 'text-white' : 'text-gray-600' }}">
                            {{ $message->user ? $message->user->initials : '?' }}
                        </div>
                    @else
                        <div class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    @endif
                </div>

                {{-- Content --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-baseline gap-2 mb-0.5">
                        <span class="text-xs font-semibold text-gray-700">
                            {{ $message->user ? $message->user->name : __('common.system_label') }}
                        </span>
                        <span class="text-xs text-gray-400">{{ $message->created_at->diffForHumans() }}</span>
                        @if($message->isComment())
                            <span class="ms-auto text-xs px-1.5 py-0.5 bg-purple-50 text-purple-600 rounded font-medium">{{ __('common.comment_label') }}</span>
                        @elseif($message->isSystem())
                            <span class="ms-auto text-xs px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded font-medium">{{ __('common.system_label') }}</span>
                        @endif
                    </div>
                    @if(!empty($message->metadata['changes']))
                        <ul class="space-y-0.5">
                            @foreach($message->metadata['changes'] as $change)
                            <li class="text-sm">
                                <span class="text-gray-400">{{ $change['from'] }}</span>
                                <span class="text-gray-300"> -&gt; </span>
                                <span class="text-blue-600 font-medium">{{ $change['to'] }}</span>
                                <span class="text-gray-400 text-xs">({{ $change['label'] }})</span>
                            </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-600 whitespace-pre-line">{{ $message->body }}</p>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-400 text-center py-4">{{ $emptyText }}</p>
        @endforelse
    </div>
</div>
