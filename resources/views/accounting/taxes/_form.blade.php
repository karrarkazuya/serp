@php $tax ??= null; $val = fn($field, $default = '') => old($field, $tax?->{$field} ?? $default); @endphp

@if($errors->any())
<div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
    <p class="text-sm font-medium text-red-700 mb-1">{{ __('accounting.fix_errors') }}</p>
    <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

@if(!isset($tax))
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
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">{{ __('accounting.field_name') }} <span class="text-red-500">*</span></label>
    <div class="flex-1">
        <input type="text" name="name" value="{{ $val('name') }}" required maxlength="255"
               class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
        @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div class="flex items-start gap-4 py-3 border-b border-gray-100">
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">{{ __('accounting.col_type') }} <span class="text-red-500">*</span></label>
    <div class="flex-1">
        <select name="amount_type" required
                class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400 bg-white">
            @foreach($amountTypes as $key => $label)
            <option value="{{ $key }}" @selected($val('amount_type', 'percent') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('amount_type')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div class="flex items-start gap-4 py-3 border-b border-gray-100">
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">{{ __('accounting.field_tax_amount') }} <span class="text-red-500">*</span></label>
    <div class="flex-1">
        <input type="number" name="amount" value="{{ $val('amount', '0') }}" step="0.0001" min="0" required
               class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
        @error('amount')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div class="flex items-start gap-4 py-3 border-b border-gray-100">
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">{{ __('accounting.col_applies_to') }} <span class="text-red-500">*</span></label>
    <div class="flex-1">
        <select name="type_tax_use" required
                class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400 bg-white">
            @foreach($typeTaxUse as $key => $label)
            <option value="{{ $key }}" @selected($val('type_tax_use', 'sale') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type_tax_use')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div class="flex items-start gap-4 py-3 border-b border-gray-100">
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">{{ __('accounting.col_tax_account') }}</label>
    <div class="flex-1">
        <x-relation-dropdown
            table="accounts"
            field="name"
            name="account_id"
            :value="old('account_id', $tax?->account_id ?? '')"
            placeholder="None" />
    </div>
</div>

<div class="flex items-start gap-4 py-3 border-b border-gray-100">
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">{{ __('accounting.field_notes') }}</label>
    <div class="flex-1">
        <textarea name="description" rows="2" maxlength="500"
                  class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400 resize-none">{{ $val('description') }}</textarea>
    </div>
</div>

<div class="flex items-start gap-4 py-3 border-b border-gray-100">
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-2">{{ __('accounting.field_include_base') }}</label>
    <div class="flex-1 pt-1.5">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="include_base_amount" value="0">
            <input type="checkbox" name="include_base_amount" value="1"
                   @checked((bool) old('include_base_amount', $tax?->include_base_amount ?? false))
                   class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
            <span class="text-sm text-gray-600">{{ __('accounting.tax_included_in_price') }}</span>
        </label>
    </div>
</div>

<div class="flex items-start gap-4 py-3">
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-2">{{ __('accounting.field_active') }}</label>
    <div class="flex-1 pt-1.5">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1"
                   @checked((bool) old('active', $tax?->active ?? true))
                   class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
            <span class="text-sm text-gray-600">{{ __('accounting.status_active') }}</span>
        </label>
    </div>
</div>
