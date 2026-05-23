@extends('layouts.app')
@section('title', 'Inventory')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-200 bg-white shrink-0">
        <h1 class="text-xl font-semibold text-gray-800">Inventory Overview</h1>
    </div>

    <div class="flex-1 overflow-y-auto p-4 sm:p-6">
        {{-- Operation Type Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            @foreach($operationTypes as $opType)
            <a href="{{ route('inventory.transfers.index', ['operation_type_id' => $opType->id]) }}"
               class="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md hover:border-purple-300 transition-all p-5 group">
                <div class="flex items-start justify-between mb-3">
                    <div class="p-2.5 rounded-lg bg-purple-50 group-hover:bg-purple-100 transition-colors">
                        @if($opType->code === 'incoming')
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                        @elseif($opType->code === 'outgoing')
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        @else
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        @endif
                    </div>
                    @if($opType->ready_count > 0)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">
                        {{ $opType->ready_count }} Ready
                    </span>
                    @endif
                </div>
                <h3 class="text-sm font-semibold text-gray-900">{{ $opType->name }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ $opType->warehouse?->name }}</p>
                <div class="flex gap-4 mt-3 text-xs text-gray-500">
                    @if(($opType->ready_count ?? 0) > 0)
                    <span class="font-medium text-green-600">{{ $opType->ready_count }} to process</span>
                    @endif
                    @if(($opType->waiting_count ?? 0) > 0)
                    <span>{{ $opType->waiting_count }} waiting</span>
                    @endif
                    @if(($opType->late_count ?? 0) > 0)
                    <span class="text-red-500">{{ $opType->late_count }} late</span>
                    @endif
                </div>
            </a>
            @endforeach
        </div>

        {{-- Quick stats row --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <a href="{{ route('inventory.replenishment.index') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-all p-4 text-center">
                <p class="text-2xl font-bold text-gray-900">{{ $replenishCount }}</p>
                <p class="text-xs text-gray-500 mt-1">To Replenish</p>
            </a>
            <a href="{{ route('inventory.products.index') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-all p-4 text-center">
                <p class="text-2xl font-bold text-gray-900">{{ $productCount }}</p>
                <p class="text-xs text-gray-500 mt-1">Products</p>
            </a>
            <a href="{{ route('inventory.lots.index') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-all p-4 text-center">
                <p class="text-2xl font-bold text-gray-900">{{ $lotCount }}</p>
                <p class="text-xs text-gray-500 mt-1">Lots / Serial Numbers</p>
            </a>
            <a href="{{ route('inventory.reports.stock') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-all p-4 text-center">
                <p class="text-2xl font-bold text-gray-900">{{ $stockLines }}</p>
                <p class="text-xs text-gray-500 mt-1">Stock Lines</p>
            </a>
        </div>
    </div>
</div>
@endsection
