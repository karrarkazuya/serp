@extends('layouts.app')
@section('title', $incoterm->code . ' — ' . $incoterm->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    @can('create', \App\Models\Accounting\AccountingIncoterm::class)
        @php $newHref = route('accounting.incoterms.create'); @endphp
    @endcan
    <x-toolbar
        :new-href="$newHref ?? null"
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('accounting.incoterms.show', $prevId) : null"
        :next-href="$nextId ? route('accounting.incoterms.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <a href="{{ route('accounting.incoterms.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Incoterms</a>
            <span class="text-sm font-semibold text-gray-800">{{ $incoterm->code }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('update', $incoterm)
                <a href="{{ route('accounting.incoterms.edit', $incoterm) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">Edit</a>
                @endcan
                @can('delete', $incoterm)
                <form method="POST" action="{{ route('accounting.incoterms.delete', $incoterm) }}" x-data="{ confirming: false }">
                    @csrf @method('DELETE')
                    <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">Delete</button>
                    <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                        <span class="text-xs text-red-600">Delete this incoterm?</span>
                        <button type="submit" class="px-2 py-1 text-xs font-medium text-white bg-red-600 rounded">Yes</button>
                        <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500">Cancel</button>
                    </div>
                </form>
                @endcan
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @if(session('success'))
        <div class="mb-4 px-3 py-2 bg-green-50 border border-green-200 text-sm text-green-700 rounded">{{ session('success') }}</div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 max-w-2xl">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ $incoterm->code }} — {{ $incoterm->name }}</h1>

            @foreach([
                ['Code',        $incoterm->code],
                ['Name',        $incoterm->name],
                ['Created by',  $incoterm->creator?->name ?? '—'],
                ['Updated by',  $incoterm->updater?->name ?? '—'],
            ] as [$label, $value])
            <div class="flex items-start gap-4 py-2 border-b border-gray-100">
                <span class="w-36 shrink-0 text-sm font-medium text-gray-500">{{ $label }}</span>
                <span class="flex-1 text-sm text-gray-800">{{ $value }}</span>
            </div>
            @endforeach
        </div>

        <x-chatter
            model-type="App\Models\Accounting\AccountingIncoterm"
            :model-id="$incoterm->id"
            :can-comment="auth()->user()->can('comment', $incoterm)"
        />
    </div>
</div>
@endsection
