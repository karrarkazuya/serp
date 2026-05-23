@php $val = fn($field, $default = '') => old($field, $currencyRate?->{$field} ?? $default); @endphp

@if($errors->any())
<div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
    <p class="text-sm font-medium text-red-700 mb-1">{{ __('accounting.fix_errors') }}</p>
    <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

@if(!isset($currencyRate))
<div class="flex items-start gap-4 py-3 border-b border-gray-100">
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">{{ __('accounting.field_company') }} <span class="text-red-500">*</span></label>
    <div class="flex-1">
        <x-relation-dropdown
            table="companies"
            field="name"
            name="company_id"
            :value="old('company_id', $defaultCompanyId ?? '')"
            :placeholder="__('accounting.ph_select_company')"
            required />
        @error('company_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
    </div>
</div>
@endif

<div class="flex items-start gap-4 py-3 border-b border-gray-100">
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">{{ __('accounting.field_currency_code') }} <span class="text-red-500">*</span></label>
    <div class="flex-1">
        <input type="text" name="currency" value="{{ $val('currency') }}" required maxlength="10" placeholder="e.g. USD, EUR"
               class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-purple-400">
        @error('currency')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div class="flex items-start gap-4 py-3 border-b border-gray-100">
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">{{ __('accounting.field_rate') }} <span class="text-red-500">*</span></label>
    <div class="flex-1">
        <input type="number" name="rate" value="{{ $val('rate') }}" required step="0.000001" min="0.000001"
               class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
        <p class="mt-1 text-xs text-gray-400">{{ __('accounting.field_rate_hint') }}</p>
        @error('rate')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div class="flex items-start gap-4 py-3 border-b border-gray-100">
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">{{ __('accounting.col_effective_date') }} <span class="text-red-500">*</span></label>
    <div class="flex-1">
        <input type="date" name="date" value="{{ $val('date', now()->toDateString()) }}" required
               class="w-48 border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
        @error('date')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div class="flex items-start gap-4 py-3">
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-2">{{ __('accounting.field_active') }}</label>
    <div class="flex-1 pt-1.5">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1"
                   @checked((bool) old('active', $currencyRate?->active ?? true))
                   class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
            <span class="text-sm text-gray-600">{{ __('accounting.status_active') }}</span>
        </label>
    </div>
</div>
