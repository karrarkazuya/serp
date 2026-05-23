@extends('layouts.app')
@section('title', __('accounting.payments'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
<form id="payment-form" method="POST" action="{{ route('accounting.payments.store') }}">
@csrf

<x-toolbar>
    <x-slot:breadcrumb>
        <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
        <a href="{{ route('accounting.payments.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.payments') }}</a>
        <span class="text-sm font-semibold text-gray-800">{{ __('accounting.payments') }}</span>
    </x-slot:breadcrumb>
    <x-slot:actions>
        <div class="flex items-center gap-2">
            <a href="{{ route('accounting.payments.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('accounting.btn_discard') }}</a>
            <button type="submit" class="px-4 py-1.5 text-sm font-semibold text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('accounting.btn_save') }}</button>
        </div>
    </x-slot:actions>
</x-toolbar>

<div class="flex-1 overflow-y-auto">
    @if($errors->any())
    <div class="mx-4 mt-4 px-4 py-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif
    @if(session('error'))
    <div class="mx-4 mt-4 px-4 py-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm">
        {{-- Title + Status pipeline --}}
        <div class="flex items-center justify-between px-6 pt-5 pb-3 border-b border-gray-100">
            <h2 class="text-2xl font-bold text-gray-800">{{ __('accounting.status_draft') }}</h2>
            <div class="flex items-center text-xs font-medium select-none">
                <span class="px-3 py-1 bg-[#714B67] text-white rounded-l-full">{{ __('accounting.status_draft') }}</span>
                <span class="px-3 py-1 bg-gray-100 text-gray-400 clip-chevron border-t border-b border-gray-200">{{ __('accounting.status_in_process') }}</span>
                <span class="px-3 py-1 bg-gray-100 text-gray-400 rounded-r-full border-t border-b border-r border-gray-200">{{ __('accounting.status_paid') }}</span>
            </div>
        </div>

        {{-- Two-column form --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-10 px-6 py-4">

            {{-- LEFT column --}}
            <div>
                {{-- Payment Type --}}
                <div class="flex items-center gap-4 py-3 border-b border-gray-100">
                    <span class="w-44 shrink-0 text-sm font-medium text-gray-600">{{ __('accounting.field_payment_type') }}</span>
                    <div class="flex items-center gap-5">
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="payment_type" value="outbound"
                                   {{ old('payment_type', 'inbound') === 'outbound' ? 'checked' : '' }}
                                   class="text-[#714B67] focus:ring-[#714B67]">
                            <span class="text-sm text-gray-700">{{ __('accounting.payment_send') }}</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="payment_type" value="inbound"
                                   {{ old('payment_type', 'inbound') === 'inbound' ? 'checked' : '' }}
                                   class="text-[#714B67] focus:ring-[#714B67]">
                            <span class="text-sm text-gray-700">{{ __('accounting.payment_receive') }}</span>
                        </label>
                    </div>
                </div>

                {{-- Customer / Partner --}}
                <div class="flex items-center gap-4 py-3 border-b border-gray-100">
                    <span class="w-44 shrink-0 text-sm font-medium text-gray-600">{{ __('accounting.field_partner') }}</span>
                    <div class="flex-1">
                        <x-relation-dropdown name="partner_id" table="contacts" field="name"
                            :value="old('partner_id')" :placeholder="__('accounting.ph_search_partner')" />
                    </div>
                </div>

                {{-- Amount + Currency --}}
                <div class="flex items-center gap-4 py-3 border-b border-gray-100">
                    <span class="w-44 shrink-0 text-sm font-medium text-gray-600">{{ __('accounting.field_amount') }} <span class="text-red-500">*</span></span>
                    <div class="flex items-center gap-2 flex-1">
                        <input type="number" name="amount" value="{{ old('amount', '0.000') }}"
                               step="0.001" min="0"
                               class="w-40 border-0 border-b border-gray-300 focus:border-[#714B67] focus:outline-none focus:ring-0 text-sm px-0 py-1 tabular-nums"
                               required>
                        <input type="text" name="currency" value="{{ old('currency', 'IQD') }}"
                               class="w-16 border-0 border-b border-gray-300 focus:border-[#714B67] focus:outline-none focus:ring-0 text-sm px-0 py-1 font-mono uppercase"
                               :placeholder="__('accounting.ph_iqd')">
                    </div>
                </div>

                {{-- Date --}}
                <div class="flex items-center gap-4 py-3 border-b border-gray-100">
                    <span class="w-44 shrink-0 text-sm font-medium text-gray-600">{{ __('accounting.field_date') }} <span class="text-red-500">*</span></span>
                    <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}"
                           class="border-0 border-b border-gray-300 focus:border-[#714B67] focus:outline-none focus:ring-0 text-sm px-0 py-1"
                           required>
                </div>

                {{-- Bank Reference --}}
                <div class="flex items-center gap-4 py-3 border-b border-gray-100">
                    <span class="w-44 shrink-0 text-sm font-medium text-gray-600">{{ __('accounting.field_bank_reference') }}</span>
                    <input type="text" name="bank_reference" value="{{ old('bank_reference') }}"
                           class="flex-1 border-0 border-b border-gray-300 focus:border-[#714B67] focus:outline-none focus:ring-0 text-sm px-0 py-1"
                           placeholder=" ">
                </div>

                {{-- Cheque Reference --}}
                <div class="flex items-center gap-4 py-3 border-b border-gray-100">
                    <span class="w-44 shrink-0 text-sm font-medium text-gray-600">{{ __('accounting.field_cheque_number') }}</span>
                    <input type="text" name="cheque_number" value="{{ old('cheque_number') }}"
                           class="flex-1 border-0 border-b border-gray-300 focus:border-[#714B67] focus:outline-none focus:ring-0 text-sm px-0 py-1"
                           placeholder=" ">
                </div>

                {{-- Memo --}}
                <div class="flex items-center gap-4 py-3">
                    <span class="w-44 shrink-0 text-sm font-medium text-gray-600">{{ __('accounting.field_memo') }}</span>
                    <input type="text" name="memo" value="{{ old('memo') }}"
                           class="flex-1 border-0 border-b border-gray-300 focus:border-[#714B67] focus:outline-none focus:ring-0 text-sm px-0 py-1"
                           placeholder=" ">
                </div>
            </div>

            {{-- RIGHT column --}}
            <div>
                {{-- Journal --}}
                <div class="flex items-center gap-4 py-3 border-b border-gray-100">
                    <span class="w-44 shrink-0 text-sm font-medium text-gray-600">{{ __('accounting.field_journal') }} <span class="text-red-500">*</span></span>
                    <div class="flex-1 border-l-2 border-[#714B67] pl-2">
                        <x-relation-dropdown name="journal_id" table="account_journals" field="name"
                            :value="old('journal_id')" placeholder="Select journal…" required />
                    </div>
                </div>

                {{-- Payment Method --}}
                <div class="flex items-center gap-4 py-3 border-b border-gray-100">
                    <span class="w-44 shrink-0 text-sm font-medium text-gray-600">{{ __('accounting.field_payment_method') }}</span>
                    <div class="flex-1 border-l-2 border-[#714B67] pl-2">
                        <select name="payment_method"
                                class="w-full border-0 bg-transparent focus:outline-none focus:ring-0 text-sm py-1 text-gray-700">
                            @foreach($paymentMethods as $key => $label)
                            <option value="{{ $key }}" {{ old('payment_method', 'manual') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Destination Account --}}
                <div class="flex items-center gap-4 py-3 border-b border-gray-100">
                    <span class="w-44 shrink-0 text-sm font-medium text-gray-600">{{ __('accounting.field_destination_acct') }}</span>
                    <div class="flex-1">
                        <x-relation-dropdown name="destination_account_id" table="accounts" field="name"
                            :value="old('destination_account_id')" placeholder="Auto (from journal)" />
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
</form>
</div>
@endsection
