@extends('layouts.app')
@section('title', $paymentTerm->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    @can('create', \App\Models\Accounting\AccountingPaymentTerm::class)
        @php $newHref = route('accounting.payment-terms.create'); @endphp
    @endcan
    <x-toolbar
        :new-href="$newHref ?? null"
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('accounting.payment-terms.show', $prevId) : null"
        :next-href="$nextId ? route('accounting.payment-terms.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <a href="{{ route('accounting.payment-terms.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Payment Terms</a>
            <span class="text-sm font-semibold text-gray-800">{{ $paymentTerm->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('update', $paymentTerm)
                <a href="{{ route('accounting.payment-terms.edit', $paymentTerm) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">Edit</a>
                @if($paymentTerm->active)
                <form method="POST" action="{{ route('accounting.payment-terms.archive', $paymentTerm) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">Archive</button>
                </form>
                @else
                <form method="POST" action="{{ route('accounting.payment-terms.unarchive', $paymentTerm) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">Restore</button>
                </form>
                @endif
                @endcan
                @can('delete', $paymentTerm)
                <form method="POST" action="{{ route('accounting.payment-terms.delete', $paymentTerm) }}" x-data="{ confirming: false }">
                    @csrf @method('DELETE')
                    <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">Delete</button>
                    <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                        <span class="text-xs text-red-600">Delete this payment term?</span>
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
                <h1 class="text-2xl font-bold text-gray-900">{{ $paymentTerm->name }}</h1>
                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $paymentTerm->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $paymentTerm->active ? 'Active' : 'Archived' }}
                </span>
            </div>

            @foreach([
                ['Company',     $paymentTerm->company?->name ?? '—'],
                ['Note',        $paymentTerm->note ?: '—'],
                ['Created by',  $paymentTerm->creator?->name ?? '—'],
                ['Updated by',  $paymentTerm->updater?->name ?? '—'],
            ] as [$label, $value])
            <div class="flex items-start gap-4 py-2 border-b border-gray-100">
                <span class="w-36 shrink-0 text-sm font-medium text-gray-500">{{ $label }}</span>
                <span class="flex-1 text-sm text-gray-800">{{ $value }}</span>
            </div>
            @endforeach

            @if($paymentTerm->lines->isNotEmpty())
            <div class="mt-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Payment Lines</h2>
                <table class="w-full text-sm border border-gray-200 rounded">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Type</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Value</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Days</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($paymentTerm->lines as $line)
                        <tr class="border-t border-gray-100">
                            <td class="px-3 py-2 capitalize">{{ $line->value_type }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ number_format((float)$line->value, 2) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ $line->days }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        <x-chatter
            model-type="App\Models\Accounting\AccountingPaymentTerm"
            :model-id="$paymentTerm->id"
            :can-comment="auth()->user()->can('comment', $paymentTerm)"
        />
    </div>
</div>
@endsection
