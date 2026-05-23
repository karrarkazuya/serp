@extends('layouts.app')
@section('title', __('accounting.incoterms'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar :new-href="auth()->user()->hasPermission('accounting.create') ? route('accounting.incoterms.create') : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('accounting.incoterms') }}</span>
        </x-slot:breadcrumb>
        <x-slot:search>
            <x-search :model="\App\Models\Accounting\AccountingIncoterm::class" />
        </x-slot:search>
    </x-toolbar>

    @if(session('success'))<div class="shrink-0 px-4 py-2 bg-green-50 border-b border-green-200 text-sm text-green-700">{{ session('success') }}</div>@endif

    @if(isset($groups))
    <x-list :grouped="true" :empty-text="__('accounting.no_incoterms')">
        <x-slot:columns>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_code') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_name') }}</th>
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
            @foreach($group['items'] as $incoterm)
            <tr x-show="open" class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('accounting.incoterms.show', $incoterm) }}'">
                <td class="px-4 py-3 text-sm font-mono font-semibold text-[#71639e]">{{ $incoterm->code }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ $incoterm->name }}</td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('accounting.no_incoterms') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$incoterms" :empty-text="__('accounting.no_incoterms')">
            <x-slot:columns>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_code') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_name') }}</th>
            </x-slot:columns>

            @foreach($incoterms as $incoterm)
            <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('accounting.incoterms.show', $incoterm) }}'">
                <td class="px-4 py-3 text-sm font-mono font-semibold text-[#71639e]">{{ $incoterm->code }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ $incoterm->name }}</td>
            </tr>
            @endforeach
        </x-list>
    @endif
</div>
@endsection
