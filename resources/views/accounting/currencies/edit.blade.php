@extends('layouts.app')
@section('title', __('accounting.exchange_rates'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <form method="POST" action="{{ route('accounting.currencies.update', $currencyRate) }}">
        @csrf @method('PUT')
        <x-toolbar>
            <x-slot:breadcrumb>
                <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
                <a href="{{ route('accounting.currencies.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.exchange_rates') }}</a>
                <a href="{{ route('accounting.currencies.show', $currencyRate) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $currencyRate->currency }}</a>
                <span class="text-sm font-semibold text-gray-800">{{ __('accounting.btn_edit') }}</span>
            </x-slot:breadcrumb>
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <a href="{{ route('accounting.currencies.show', $currencyRate) }}" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">{{ __('accounting.btn_cancel') }}</a>
                    <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-[#71639e] hover:bg-[#5c527f] rounded">{{ __('accounting.btn_save') }}</button>
                </div>
            </x-slot:actions>
        </x-toolbar>

        <div class="flex-1 overflow-y-auto p-4">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 max-w-2xl">
                @include('accounting.currencies._form')
            </div>
        </div>
    </form>
</div>
@endsection
