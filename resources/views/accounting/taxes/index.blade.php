@extends('layouts.app')
@section('title', 'Taxes')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar :new-href="auth()->user()->hasPermission('accounting.create') ? route('accounting.taxes.create') : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Taxes</span>
        </x-slot:breadcrumb>
        <x-slot:search>
            <x-search :model="\App\Models\Accounting\AccountTax::class"
                :quickFilters="[
                    ['label' => 'Sales', 'url' => route('accounting.taxes.index', ['type_tax_use' => 'sale'])],
                    ['label' => 'Purchases', 'url' => route('accounting.taxes.index', ['type_tax_use' => 'purchase'])],
                    ['label' => 'Archived', 'url' => route('accounting.taxes.index', ['filter' => 'archived'])],
                ]" />
        </x-slot:search>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @if(session('success'))
        <div class="mb-4 px-3 py-2 bg-green-50 border border-green-200 text-sm text-green-700 rounded">{{ session('success') }}</div>
        @endif

        <x-list :paginator="$taxes" empty-text="No taxes found.">
            <x-slot:columns>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Rate / Amount</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Applies To</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Tax Account</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
            </x-slot:columns>

            @foreach($taxes as $tax)
            <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('accounting.taxes.show', $tax) }}'">
                <td class="px-4 py-3 text-sm font-semibold text-[#71639e]">{{ $tax->name }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ \App\Models\Accounting\AccountTax::AMOUNT_TYPES[$tax->amount_type] ?? $tax->amount_type }}</td>
                <td class="px-4 py-3 text-sm tabular-nums text-gray-700">
                    {{ $tax->amount_type === 'percent' ? number_format((float)$tax->amount, 2) . '%' : number_format((float)$tax->amount, 2) }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ \App\Models\Accounting\AccountTax::TYPE_TAX_USE[$tax->type_tax_use] ?? $tax->type_tax_use }}</td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ $tax->account?->display_name ?? '—' }}</td>
                <td class="px-4 py-3">
                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $tax->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $tax->active ? 'Active' : 'Archived' }}
                    </span>
                </td>
            </tr>
            @endforeach
        </x-list>
    </div>
</div>
@endsection
