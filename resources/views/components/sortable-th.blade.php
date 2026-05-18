@props([
    'column',
    'label',
    'align' => 'left',
    'class' => '',
    'default' => false,
])

@php
    $currentSort = request('sort');
    $currentDirection = request('direction') === 'desc' ? 'desc' : 'asc';
    $active = $currentSort ? $currentSort === $column : (bool) $default;
    $nextDirection = $active && $currentDirection === 'asc' ? 'desc' : 'asc';
    $query = array_merge(request()->except('page'), [
        'sort' => $column,
        'direction' => $nextDirection,
    ]);
    $url = request()->url() . '?' . http_build_query($query);
    $alignment = $align === 'right' ? 'justify-end text-right' : 'justify-start text-left';
@endphp

<th {{ $attributes->merge(['class' => trim("px-4 py-2.5 text-xs font-semibold uppercase tracking-wide {$class}")]) }}>
    <a href="{{ $url }}"
       class="group inline-flex w-full items-center gap-1.5 {{ $alignment }} {{ $active ? 'text-gray-800' : 'text-gray-500 hover:text-gray-800' }}">
        <span>{{ $label }}</span>
        @if($active && $currentDirection === 'desc')
            <svg class="h-3.5 w-3.5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
            </svg>
        @elseif($active)
            <svg class="h-3.5 w-3.5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/>
            </svg>
        @else
            <svg class="h-3.5 w-3.5 text-gray-300 opacity-0 transition-opacity group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/>
            </svg>
        @endif
    </a>
</th>
