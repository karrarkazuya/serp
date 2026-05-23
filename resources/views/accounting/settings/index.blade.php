@extends('layouts.app')
@section('title', 'Accounting Settings')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Settings & Lock Dates</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        @if(session('success'))
        <div class="px-3 py-2 bg-green-50 border border-green-200 text-sm text-green-700 rounded">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="px-3 py-2 bg-red-50 border border-red-200 text-sm text-red-700 rounded">{{ session('error') }}</div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-1">Lock Dates</h2>
            <p class="text-sm text-gray-500 mb-6">
                Lock dates prevent posting journal entries in closed periods.
                The <strong>fiscal year lock</strong> is a hard lock — no one can post before it.
                The <strong>period lock</strong> is a soft lock — only users with the <em>Lock Bypass</em> permission can post in a locked period.
            </p>

            @foreach($companies as $company)
            <div class="border border-gray-200 rounded-lg p-5 mb-4 last:mb-0">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-800">{{ $company->name }}</h3>
                        <p class="text-xs text-gray-400">Base currency: {{ $company->currency ?: 'IQD' }}</p>
                    </div>
                </div>

                @if(!auth()->user()->hasPermission('accounting.lock'))
                <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2 mb-4">
                    You need the <strong>Lock Dates</strong> permission to modify these settings.
                </div>
                @endif

                <form method="POST" action="{{ route('accounting.settings.update', $company) }}">
                    @csrf @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                Period Lock Date
                                <span class="font-normal text-gray-400 ms-1">(soft lock)</span>
                            </label>
                            <input type="date"
                                   name="accounting_period_lock_date"
                                   value="{{ $company->accounting_period_lock_date?->format('Y-m-d') }}"
                                   @disabled(!auth()->user()->hasPermission('accounting.lock'))
                                   class="w-full text-sm border border-gray-300 rounded px-3 py-2 focus:border-purple-500 focus:outline-none focus:ring-0 disabled:bg-gray-50 disabled:text-gray-400">
                            <p class="mt-1 text-xs text-gray-400">Accountants with lock-bypass permission can still post before this date.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                Fiscal Year Lock Date
                                <span class="font-normal text-red-400 ms-1">(hard lock)</span>
                            </label>
                            <input type="date"
                                   name="accounting_fiscal_year_lock_date"
                                   value="{{ $company->accounting_fiscal_year_lock_date?->format('Y-m-d') }}"
                                   @disabled(!auth()->user()->hasPermission('accounting.lock'))
                                   class="w-full text-sm border border-gray-300 rounded px-3 py-2 focus:border-purple-500 focus:outline-none focus:ring-0 disabled:bg-gray-50 disabled:text-gray-400">
                            <p class="mt-1 text-xs text-gray-400">No one can post entries on or before this date. Use with caution.</p>
                        </div>
                    </div>

                    @if(auth()->user()->hasPermission('accounting.lock'))
                    <div class="mt-4 flex justify-end">
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-[#71639e] hover:bg-[#5c527f] rounded">
                            Save Lock Dates for {{ $company->name }}
                        </button>
                    </div>
                    @endif
                </form>
            </div>
            @endforeach

            @if($companies->isEmpty())
            <p class="text-sm text-gray-400">No active companies found.</p>
            @endif
        </div>
    </div>
</div>
@endsection
