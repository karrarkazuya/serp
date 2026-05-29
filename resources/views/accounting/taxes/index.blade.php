@extends('layouts.app')
@section('title', __('accounting.taxes'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar :new-href="auth()->user()->hasPermission('accounting.create') ? route('accounting.taxes.create') : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('accounting.taxes') }}</span>
        </x-slot:breadcrumb>
        <x-slot:search>
            <x-search :model="\App\Models\Accounting\AccountTax::class"
                {{-- Tax-quickfilter chips were hardcoded English; routed through __() like every other localized label. --}}
                :quickFilters="[
                    ['label' => __('accounting.journal_type_sales'),    'url' => route('accounting.taxes.index', ['type_tax_use' => 'sale'])],
                    ['label' => __('accounting.journal_type_purchase'), 'url' => route('accounting.taxes.index', ['type_tax_use' => 'purchase'])],
                    ['label' => __('common.archived'),                  'url' => route('accounting.taxes.index', ['filter' => 'archived'])],
                ]" />
        </x-slot:search>
    </x-toolbar>

    @if(session('success'))<div class="shrink-0 px-4 py-2 bg-green-50 border-b border-green-200 text-sm text-green-700">{{ session('success') }}</div>@endif

    @if(isset($groups))
    <x-list :grouped="true" :empty-text="__('accounting.no_taxes')">
        <x-slot:columns>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_name') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_type') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_rate_amount') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_applies_to') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_tax_account') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_status') }}</th>
        </x-slot:columns>

        @forelse($groups as $group)
        <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="divide-y divide-gray-100">
            <tr class="bg-gray-50 border-y border-gray-200 cursor-pointer select-none" @click="open = !open">
                <td colspan="99" class="px-4 py-2.5">
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                        <svg class="w-3.5 h-3.5 transition-transform shrink-0 text-gray-400" :class="open ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        {{ $group['label'] }}
                        <span class="ms-1 text-xs text-gray-400 font-normal">({{ $group['count'] }})</span>
                    </div>
                </td>
            </tr>
            @foreach($group['items'] as $tax)
            <tr x-show="open" class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('accounting.taxes.show', $tax) }}'">
                <td class="px-4 py-3 text-sm font-semibold text-[#71639e]">{{ $tax->name }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ $tax->amount_type_label }}</td>
                <td class="px-4 py-3 text-sm tabular-nums text-gray-700">
                    {{ $tax->amount_type === 'percent' ? number_format((float)$tax->amount, 2) . '%' : number_format((float)$tax->amount, 2) }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ $tax->type_tax_use_label }}</td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ $tax->account?->display_name ?? '—' }}</td>
                <td class="px-4 py-3">
                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $tax->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $tax->active ? __('accounting.status_active') : __('accounting.status_archived') }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('accounting.no_taxes') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$taxes" :empty-text="__('accounting.no_taxes')">
            <x-slot:columns>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_name') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_type') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_rate_amount') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_applies_to') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_tax_account') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_status') }}</th>
            </x-slot:columns>

            @foreach($taxes as $tax)
            <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('accounting.taxes.show', $tax) }}'">
                <td class="px-4 py-3 text-sm font-semibold text-[#71639e]">{{ $tax->name }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ $tax->amount_type_label }}</td>
                <td class="px-4 py-3 text-sm tabular-nums text-gray-700">
                    {{ $tax->amount_type === 'percent' ? number_format((float)$tax->amount, 2) . '%' : number_format((float)$tax->amount, 2) }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ $tax->type_tax_use_label }}</td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ $tax->account?->display_name ?? '—' }}</td>
                <td class="px-4 py-3">
                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $tax->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $tax->active ? __('accounting.status_active') : __('accounting.status_archived') }}
                    </span>
                </td>
            </tr>
            @endforeach
        </x-list>
    @endif
</div>
@endsection
