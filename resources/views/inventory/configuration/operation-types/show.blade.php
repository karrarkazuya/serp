@extends('layouts.app')
@section('title', $operationType->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    @can('create', \App\Models\Inventory\OperationType::class)
        @php $newHref = route('inventory.config.operation-types.create'); @endphp
    @endcan
    <x-toolbar
        :new-href="$newHref ?? null"
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('inventory.config.operation-types.show', $prevId) : null"
        :next-href="$nextId ? route('inventory.config.operation-types.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('inventory.config.operation-types.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Operation Types</a>
            <span class="text-sm font-semibold text-gray-800">{{ $operationType->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('update', $operationType)
                <a href="{{ route('inventory.config.operation-types.edit', $operationType) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">Edit</a>
                @if($operationType->active)
                <form method="POST" action="{{ route('inventory.config.operation-types.archive', $operationType) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">Archive</button>
                </form>
                @else
                <form method="POST" action="{{ route('inventory.config.operation-types.unarchive', $operationType) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-green-700 border border-green-200 rounded hover:bg-green-50">Restore</button>
                </form>
                @endif
                @endcan
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm p-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ $operationType->name }}</h1>
            <div class="grid grid-cols-2 gap-x-8">
                <div>
                    @foreach([
                        ['Type', $operationType->code_label],
                        ['Warehouse', $operationType->warehouse?->name],
                        ['Company', $operationType->company?->name],
                        ['Default Source', $operationType->defaultSrcLocation?->complete_name],
                        ['Default Destination', $operationType->defaultDestLocation?->complete_name],
                    ] as [$label, $value])
                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <span class="w-40 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                        <span class="flex-1 text-sm text-gray-800">{{ $value ?: '-' }}</span>
                    </div>
                    @endforeach
                </div>
                <div>
                    @foreach([
                        ['Sequence Prefix', $operationType->sequence_prefix],
                        ['Reservation Method', ucwords(str_replace('_', ' ', $operationType->reservation_method))],
                        ['Create Lots', $operationType->use_create_lots ? 'Yes' : 'No'],
                        ['Use Existing Lots', $operationType->use_existing_lots ? 'Yes' : 'No'],
                    ] as [$label, $value])
                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <span class="w-40 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                        <span class="flex-1 text-sm text-gray-800">{{ $value ?: '-' }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
