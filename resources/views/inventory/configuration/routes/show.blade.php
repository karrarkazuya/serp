@extends('layouts.app')
@section('title', $route->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    @can('create', \App\Models\Inventory\Route::class)
        @php $newHref = route('inventory.config.routes.create'); @endphp
    @endcan
    <x-toolbar
        :new-href="$newHref ?? null"
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('inventory.config.routes.show', $prevId) : null"
        :next-href="$nextId ? route('inventory.config.routes.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('inventory.config.routes.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('inventory.routes') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $route->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('update', $route)
                <a href="{{ route('inventory.config.routes.edit', $route) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('inventory.edit') }}</a>
                @if($route->active)
                <form method="POST" action="{{ route('inventory.config.routes.archive', $route) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">{{ __('inventory.archive') }}</button>
                </form>
                @else
                <form method="POST" action="{{ route('inventory.config.routes.unarchive', $route) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-green-700 border border-green-200 rounded hover:bg-green-50">{{ __('inventory.restore') }}</button>
                </form>
                @endif
                @endcan
                @can('delete', $route)
                <div x-data="{ confirming: false }">
                    <form method="POST" action="{{ route('inventory.config.routes.delete', $route) }}">
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
        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm p-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ $route->name }}</h1>

            <div class="flex items-center gap-4 py-2 border-b border-gray-100 mb-6">
                <span class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.company') }}</span>
                <span class="flex-1 text-sm text-gray-800">{{ $route->company?->name }}</span>
            </div>

            @if($route->rules->isNotEmpty())
            <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('inventory.section_rules') }}</h3>
            <table class="w-full text-sm">
                <thead><tr class="border-b border-gray-200">
                    <th class="py-2 text-start text-xs text-gray-500">{{ __('inventory.name') }}</th>
                    <th class="py-2 text-start text-xs text-gray-500">{{ __('inventory.col_action') }}</th>
                    <th class="py-2 text-start text-xs text-gray-500">{{ __('inventory.col_operation_type') }}</th>
                    <th class="py-2 text-start text-xs text-gray-500">{{ __('inventory.col_source') }}</th>
                    <th class="py-2 text-start text-xs text-gray-500">{{ __('inventory.destination') }}</th>
                </tr></thead>
                <tbody>
                    @foreach($route->rules->sortBy('sequence') as $rule)
                    <tr class="border-b border-gray-100">
                        <td class="py-2 text-gray-800 font-medium">{{ $rule->name }}</td>
                        <td class="py-2 text-gray-600">{{ ucfirst($rule->action) }}</td>
                        <td class="py-2 text-gray-600">{{ $rule->operationType?->name }}</td>
                        <td class="py-2 text-gray-600">{{ $rule->sourceLocation?->complete_name ?? __('inventory.any') }}</td>
                        <td class="py-2 text-gray-600">{{ $rule->destLocation?->complete_name ?? __('inventory.any') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

        <div class="bg-white mx-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Inventory\Route"
                :model-id="$route->id"
                :can-comment="auth()->user()->can('update', $route)"
            />
        </div>
    </div>
</div>
@endsection
