@extends('layouts.app')
@section('title', $tax->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    @can('create', \App\Models\Accounting\AccountTax::class)
        @php $newHref = route('accounting.taxes.create'); @endphp
    @endcan
    <x-toolbar
        :new-href="$newHref ?? null"
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('accounting.taxes.show', $prevId) : null"
        :next-href="$nextId ? route('accounting.taxes.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <a href="{{ route('accounting.taxes.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Taxes</a>
            <span class="text-sm font-semibold text-gray-800">{{ $tax->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('update', $tax)
                <a href="{{ route('accounting.taxes.edit', $tax) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">Edit</a>
                @if($tax->active)
                <form method="POST" action="{{ route('accounting.taxes.archive', $tax) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">Archive</button>
                </form>
                @else
                <form method="POST" action="{{ route('accounting.taxes.unarchive', $tax) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">Restore</button>
                </form>
                @endif
                @endcan
                @can('delete', $tax)
                <form method="POST" action="{{ route('accounting.taxes.delete', $tax) }}" x-data="{ confirming: false }">
                    @csrf @method('DELETE')
                    <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">Delete</button>
                    <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                        <span class="text-xs text-red-600">Delete this tax?</span>
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
            <div class="flex items-center gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">{{ $tax->name }}</h1>
                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $tax->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $tax->active ? 'Active' : 'Archived' }}
                </span>
            </div>

            @foreach([
                ['Tax Type',       $amountTypes[$tax->amount_type] ?? $tax->amount_type],
                ['Rate / Amount',  $tax->amount_type === 'percent' ? number_format((float)$tax->amount, 2).'%' : number_format((float)$tax->amount, 2)],
                ['Applies To',     $typeTaxUse[$tax->type_tax_use] ?? $tax->type_tax_use],
                ['Tax Account',    $tax->account?->display_name ?? '—'],
                ['Price Inclusive',$tax->include_base_amount ? 'Yes (tax included in price)' : 'No (tax added on top)'],
                ['Description',    $tax->description ?: '—'],
                ['Company',        $tax->company?->name ?? '—'],
                ['Created by',     $tax->creator?->name ?? '—'],
                ['Last updated by',$tax->updater?->name ?? '—'],
            ] as [$label, $value])
            <div class="flex items-start gap-4 py-2 border-b border-gray-100">
                <span class="w-36 shrink-0 text-sm font-medium text-gray-500">{{ $label }}</span>
                <span class="flex-1 text-sm text-gray-800">{{ $value }}</span>
            </div>
            @endforeach
        </div>

        <x-chatter
            model-type="App\Models\Accounting\AccountTax"
            :model-id="$tax->id"
            :can-comment="auth()->user()->can('comment', $tax)"
        />
    </div>
</div>
@endsection
