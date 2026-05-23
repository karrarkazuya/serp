@extends('layouts.app')
@section('title', $location->complete_name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    @can('create', \App\Models\Inventory\Location::class)
        @php $newHref = route('inventory.config.locations.create'); @endphp
    @endcan
    <x-toolbar
        :new-href="$newHref ?? null"
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('inventory.config.locations.show', $prevId) : null"
        :next-href="$nextId ? route('inventory.config.locations.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('inventory.config.locations.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('inventory.locations') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $location->complete_name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('update', $location)
                <a href="{{ route('inventory.config.locations.edit', $location) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('inventory.edit') }}</a>
                @if($location->active)
                <form method="POST" action="{{ route('inventory.config.locations.archive', $location) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">{{ __('inventory.archive') }}</button>
                </form>
                @else
                <form method="POST" action="{{ route('inventory.config.locations.unarchive', $location) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-green-700 border border-green-200 rounded hover:bg-green-50">{{ __('inventory.restore') }}</button>
                </form>
                @endif
                @endcan
                @can('delete', $location)
                <div x-data="{ confirming: false }">
                    <form method="POST" action="{{ route('inventory.config.locations.delete', $location) }}">
                        @csrf @method('DELETE')
                        <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">{{ __('inventory.delete') }}</button>
                        <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                            <span class="text-xs text-red-600">{{ __('inventory.are_you_sure') }}</span>
                            <button type="submit" class="px-2 py-1 text-xs bg-red-600 text-white rounded">{{ __('inventory.yes') }}</button>
                            <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500 border rounded">{{ __('inventory.cancel') }}</button>
                        </div>
                    </form>
                </div>
                @endcan
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm p-6">
            @if(!$location->active)
            <div class="mb-4"><div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">{{ __('inventory.location_archived') }}</div></div>
            @endif

            <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ $location->complete_name }}</h1>
            @foreach([
                [__('inventory.parent'), $location->parent?->complete_name],
                [__('inventory.type'), $location->usage_label],
                [__('inventory.company'), $location->company?->name ?? __('inventory.all_companies')],
                [__('inventory.scrap_location'), $location->scrap_location ? __('inventory.yes') : __('inventory.no')],
                [__('inventory.return_location'), $location->return_location ? __('inventory.yes') : __('inventory.no')],
            ] as [$label, $value])
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <span class="w-40 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                <span class="flex-1 text-sm text-gray-800">{{ $value ?: '-' }}</span>
            </div>
            @endforeach

            @if($location->children->isNotEmpty())
            <div class="mt-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ __('inventory.sub_locations') }}</h3>
                @foreach($location->children as $child)
                <a href="{{ route('inventory.config.locations.show', $child) }}" class="block py-1 text-sm text-purple-600 hover:underline">{{ $child->name }}</a>
                @endforeach
            </div>
            @endif
        </div>

        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter model-type="App\Models\Inventory\Location" :model-id="$location->id"
                :can-comment="auth()->user()->can('comment', $location)" />
        </div>
    </div>
</div>
@endsection
