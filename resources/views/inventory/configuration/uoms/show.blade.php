@extends('layouts.app')
@section('title', $uom->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('inventory.config.uoms.show', $prevId) : null"
        :next-href="$nextId ? route('inventory.config.uoms.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('inventory.config.uoms.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Units of Measure</a>
            <span class="text-sm font-semibold text-gray-800">{{ $uom->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <a href="{{ route('inventory.config.uoms.edit', $uom) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">Edit</a>
                <div x-data="{ confirming: false }">
                    <form method="POST" action="{{ route('inventory.config.uoms.delete', $uom) }}">
                        @csrf @method('DELETE')
                        <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">Archive</button>
                        <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                            <span class="text-xs text-red-600">Archive this UoM?</span>
                            <button type="submit" class="px-2 py-1 text-xs bg-red-600 text-white rounded">Yes</button>
                            <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500 border rounded">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm p-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ $uom->name }}</h1>
            @foreach([
                ['Category', $uom->category?->name],
                ['Symbol', $uom->symbol],
                ['Type', ucfirst($uom->uom_type)],
                ['Ratio', $uom->ratio],
                ['Rounding', $uom->rounding],
            ] as [$label, $value])
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <span class="w-40 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                <span class="flex-1 text-sm text-gray-800">{{ $value ?: '-' }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
