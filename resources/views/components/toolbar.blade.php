<div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
    @if($newHref)
    <a href="{{ $newHref }}" class="shrink-0 px-3 py-1.5 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-medium rounded">{{ __('common.new') }}</a>
    @endif

    @isset($breadcrumb)
    <div class="shrink-0 flex flex-col leading-tight">{{ $breadcrumb }}</div>
    @endisset

    @isset($actions)
    {{ $actions }}
    @endisset

    @if($position !== null)
    <div class="ms-auto shrink-0 flex items-center gap-1 text-sm text-gray-500">
        <span class="text-xs whitespace-nowrap">{{ $position }} / {{ $total }}</span>
        @if($prevHref)
            <a href="{{ $prevHref }}" class="p-1 hover:bg-gray-100 rounded">{{ $isRtl ? '›' : '‹' }}</a>
        @else
            <span class="p-1 text-gray-300">{{ $isRtl ? '›' : '‹' }}</span>
        @endif
        @if($nextHref)
            <a href="{{ $nextHref }}" class="p-1 hover:bg-gray-100 rounded">{{ $isRtl ? '‹' : '›' }}</a>
        @else
            <span class="p-1 text-gray-300">{{ $isRtl ? '‹' : '›' }}</span>
        @endif
    </div>
    @endif
</div>
