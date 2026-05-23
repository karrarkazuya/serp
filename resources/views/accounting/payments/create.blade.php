@extends('layouts.app')
@section('title', 'New Payment')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <form method="POST" action="{{ route('accounting.payments.store') }}">
    @csrf

    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <a href="{{ route('accounting.payments.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Payments</a>
            <span class="text-sm font-semibold text-gray-800">New Payment</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <a href="{{ route('accounting.payments.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">Cancel</a>
                <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-purple-600 rounded hover:bg-purple-700">Save</button>
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @if(session('error'))
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 max-w-2xl">

            <div class="flex items-start gap-4 py-3 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">Payment Type <span class="text-red-500">*</span></label>
                <div class="flex-1">
                    <select name="payment_type"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400"
                            required>
                        <option value="inbound"  {{ old('payment_type', 'inbound')  === 'inbound'  ? 'selected' : '' }}>Inbound – Receive Money</option>
                        <option value="outbound" {{ old('payment_type', 'inbound')  === 'outbound' ? 'selected' : '' }}>Outbound – Send Money</option>
                    </select>
                    @error('payment_type')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex items-start gap-4 py-3 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">Journal <span class="text-red-500">*</span></label>
                <div class="flex-1">
                    <x-relation-dropdown
                        name="journal_id"
                        table="account_journals"
                        field="name"
                        :value="old('journal_id', '')"
                        placeholder="Select journal…"
                        required />
                    @error('journal_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex items-start gap-4 py-3 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">Partner</label>
                <div class="flex-1">
                    <x-relation-dropdown
                        name="partner_id"
                        table="contacts"
                        field="name"
                        :value="old('partner_id', '')"
                        placeholder="None" />
                </div>
            </div>

            <div class="flex items-start gap-4 py-3 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">Date <span class="text-red-500">*</span></label>
                <div class="flex-1">
                    <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}"
                           class="w-48 border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400"
                           required>
                    @error('date')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex items-start gap-4 py-3 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">Amount <span class="text-red-500">*</span></label>
                <div class="flex-1 flex gap-3">
                    <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01"
                           class="w-48 border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400"
                           placeholder="0.00" required>
                    <input type="text" name="currency" value="{{ old('currency') }}"
                           class="w-24 border border-gray-300 rounded px-3 py-1.5 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-purple-400"
                           placeholder="USD">
                </div>
                @error('amount')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-start gap-4 py-3">
                <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">Memo</label>
                <div class="flex-1">
                    <input type="text" name="memo" value="{{ old('memo') }}"
                           class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400"
                           placeholder="Payment reference…">
                </div>
            </div>

        </div>
    </div>
    </form>
</div>
@endsection
