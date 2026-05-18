{{-- Usage: @include('components.breadcrumb', ['items' => [['label' => 'Contacts', 'url' => route('contacts.index')], ['label' => 'John Doe']]]) --}}
<nav class="flex items-center text-sm text-gray-500 gap-1">
    @foreach($items as $index => $item)
        @if(!$loop->last)
            <a href="{{ $item['url'] }}" class="hover:text-purple-600 transition-colors">{{ $item['label'] }}</a>
            <svg class="w-3.5 h-3.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        @else
            <span class="text-gray-700 font-medium">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
