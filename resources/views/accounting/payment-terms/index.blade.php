@extends('layouts.app')
@section('title', 'Payment Terms')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar :new-href="auth()->user()->hasPermission('accounting.create') ? route('accounting.payment-terms.create') : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Payment Terms</span>
        </x-slot:breadcrumb>
        <x-slot:search>
            <x-search :model="\App\Models\Accounting\AccountingPaymentTerm::class" />
        </x-slot:search>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @if(session('success'))
        <div class="mb-4 px-3 py-2 bg-green-50 border border-green-200 text-sm text-green-700 rounded">{{ session('success') }}</div>
        @endif

        <x-list :paginator="$terms" empty-text="No payment terms found.">
            <x-slot:columns>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Lines</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
            </x-slot:columns>

            @foreach($terms as $term)
            <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('accounting.payment-terms.show', $term) }}'">
                <td class="px-4 py-3 text-sm font-semibold text-[#71639e]">{{ $term->name }}</td>
                <td class="px-4 py-3 text-sm text-gray-500">{{ $term->lines_count ?? '—' }}</td>
                <td class="px-4 py-3">
                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $term->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $term->active ? 'Active' : 'Archived' }}
                    </span>
                </td>
            </tr>
            @endforeach
        </x-list>
    </div>
</div>
@endsection
