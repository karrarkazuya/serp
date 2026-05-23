@extends('layouts.app')
@section('title', 'General Ledger')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">General Ledger</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @include('accounting.reports._filters', [
            'dateFrom'    => $dateFrom,
            'dateTo'      => $dateTo,
            'showJournal' => true,
            'journals'    => $journals,
            'journalId'   => $journalId,
            'showAccount' => true,
            'accounts'    => $accounts,
            'accountId'   => $accountId,
        ])

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Journal Entry</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Account</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Label</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Debit</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Credit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($lines as $line)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 tabular-nums text-gray-600">{{ $line->date?->format('Y-m-d') }}</td>
                        <td class="px-4 py-2.5 text-purple-600">
                            <a href="{{ route('accounting.moves.show', $line->move_id) }}" class="hover:underline">
                                {{ $line->move?->name ?? $line->move_id }}
                            </a>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-gray-700">{{ $line->account?->code }} {{ $line->account?->name }}</td>
                        <td class="px-4 py-2.5 text-gray-700">{{ $line->name ?: '—' }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-800">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-800">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-sm text-gray-400 text-center">No journal items found for the selected filters.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $lines->links() }}</div>
    </div>
</div>
@endsection
