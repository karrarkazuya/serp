@php $val = fn($field, $default = '') => old($field, $currencyRate?->{$field} ?? $default); @endphp

@if($errors->any())
<div class="mb-6 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
    <p class="text-sm font-medium text-red-700 mb-1">Please fix the errors below.</p>
    <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="space-y-4">
    <div class="flex items-center gap-5">
        <label class="w-40 shrink-0 text-sm font-semibold text-gray-700">Currency Code <span class="text-red-500">*</span></label>
        <input type="text" name="currency" value="{{ $val('currency') }}" required maxlength="10" placeholder="e.g. USD, EUR"
               class="flex-1 text-sm border-0 border-b border-gray-300 focus:border-purple-500 focus:outline-none focus:ring-0 px-0 py-1">
    </div>

    <div class="flex items-center gap-5">
        <label class="w-40 shrink-0 text-sm font-semibold text-gray-700">Rate <span class="text-red-500">*</span></label>
        <div class="flex-1">
            <input type="number" name="rate" value="{{ $val('rate') }}" required step="0.000001" min="0.000001"
                   class="w-full text-sm border-0 border-b border-gray-300 focus:border-purple-500 focus:outline-none focus:ring-0 px-0 py-1">
            <p class="mt-1 text-xs text-gray-400">Units of base currency (e.g. IQD) per 1 unit of the foreign currency.</p>
        </div>
    </div>

    <div class="flex items-center gap-5">
        <label class="w-40 shrink-0 text-sm font-semibold text-gray-700">Effective Date <span class="text-red-500">*</span></label>
        <input type="date" name="date" value="{{ $val('date', now()->toDateString()) }}" required
               class="flex-1 text-sm border-0 border-b border-gray-300 focus:border-purple-500 focus:outline-none focus:ring-0 px-0 py-1">
    </div>

    <div class="flex items-center gap-5">
        <label class="w-40 shrink-0 text-sm font-semibold text-gray-700">Active</label>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1"
                   @checked((bool) old('active', $currencyRate?->active ?? true))
                   class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
            <span class="text-sm text-gray-600">Active</span>
        </label>
    </div>

    @if(!isset($currencyRate))
    <div class="flex items-center gap-5">
        <label class="w-40 shrink-0 text-sm font-semibold text-gray-700">Company <span class="text-red-500">*</span></label>
        <div class="flex-1">
            <x-relation-dropdown
                table="companies"
                field="name"
                name="company_id"
                relation="many2one"
                :compact="true"
                :selected="old('company_id', $defaultCompanyId ?? null)"
            />
        </div>
    </div>
    @endif
</div>
