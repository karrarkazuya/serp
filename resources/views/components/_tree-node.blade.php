@php
    $hasChildren  = !empty($node['children']);
    $badgeClasses = [
        'green'  => 'text-green-700 bg-green-50',
        'blue'   => 'text-blue-700 bg-blue-50',
        'orange' => 'text-orange-700 bg-orange-50',
        'red'    => 'text-red-700 bg-red-50',
        'gray'   => 'text-gray-600 bg-gray-100',
    ];
    $badgeCls = $badgeClasses[$node['badge_color'] ?? 'gray'] ?? $badgeClasses['gray'];
@endphp

<div x-data="{ open: false }">
    {{-- Node card --}}
    <div class="flex items-center gap-2">

        {{-- Expand / collapse toggle (only for nodes with children) --}}
        @if($hasChildren)
        <button type="button"
                @click="open = !open"
                class="shrink-0 w-5 h-5 rounded flex items-center justify-center text-gray-400 hover:text-purple-600 hover:bg-purple-50 transition-colors">
            <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="open ? 'rotate-90' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
            </svg>
        </button>
        @else
        <span class="shrink-0 w-5 h-5"></span>
        @endif

        {{-- Card --}}
        <a href="{{ $node['url'] }}"
           class="group flex items-center gap-2.5 bg-white border border-gray-200 rounded-lg px-3 py-2 shadow-sm hover:shadow hover:border-purple-300 transition-all min-w-0">

            {{-- Avatar --}}
            @if(!empty($node['avatar']))
                <img src="{{ $node['avatar'] }}" alt="{{ $node['name'] }}"
                     class="w-8 h-8 rounded-full object-cover border border-gray-100 shrink-0">
            @else
                <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700 shrink-0">
                    {{ $node['initials'] }}
                </div>
            @endif

            {{-- Text --}}
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-900 group-hover:text-purple-700 truncate leading-tight">
                    {{ $node['name'] }}
                </p>
                @if(!empty($node['subtitle']))
                    <p class="text-xs text-gray-500 truncate leading-tight">{{ $node['subtitle'] }}</p>
                @endif
                @if(!empty($node['meta']))
                    <p class="text-xs text-gray-400 truncate leading-tight">{{ $node['meta'] }}</p>
                @endif
            </div>

            {{-- Badge --}}
            @if(!empty($node['badge']))
                <span class="shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded {{ $badgeCls }}">
                    {{ $node['badge'] }}
                </span>
            @endif

            {{-- Children count pill --}}
            @if($hasChildren)
                <span class="shrink-0 text-xs font-semibold text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded-full">
                    {{ count($node['children']) }}
                </span>
            @endif
        </a>
    </div>

    {{-- Children --}}
    @if($hasChildren)
    <div x-show="open"
         x-transition:enter="transition-all duration-150 ease-out"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="ml-9 mt-1.5 pl-4 border-l-2 border-gray-200 space-y-1.5"
         style="display:none">
        @foreach($node['children'] as $child)
            @include('components._tree-node', ['node' => $child, 'depth' => $depth + 1])
        @endforeach
    </div>
    @endif
</div>
