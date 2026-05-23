@php $tax ??= null; $val = fn($field, $default = '') => old($field, $tax?->{$field} ?? $default); @endphp

@if($errors->any())
<div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
    <p class="text-sm font-medium text-red-700 mb-1">Please fix the errors below.</p>
    <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

@if(!isset($tax))
<div class="flex items-start gap-4 py-3 border-b border-gray-100">
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">Company <span class="text-red-500">*</span></label>
    <div class="flex-1">
        <x-relation-dropdown
            table="companies"
            field="name"
            name="company_id"
            :value="old('company_id', $defaultCompanyId ?? '')"
            placeholder="Select company…"
            required />
        @error('company_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
    </div>
</div>
@endif

<div class="flex items-start gap-4 py-3 border-b border-gray-100">
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">Tax Name <span class="text-red-500">*</span></label>
    <div class="flex-1">
        <input type="text" name="name" value="{{ $val('name') }}" required maxlength="255"
               class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
        @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div class="flex items-start gap-4 py-3 border-b border-gray-100">
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">Tax Type <span class="text-red-500">*</span></label>
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
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">Amount <span class="text-red-500">*</span></label>
    <div class="flex-1">
        <input type="number" name="amount" value="{{ $val('amount', '0') }}" step="0.0001" min="0" required
               class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
        @error('amount')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div class="flex items-start gap-4 py-3 border-b border-gray-100">
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">Applies To <span class="text-red-500">*</span></label>
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
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">Tax Account</label>
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
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">Description</label>
    <div class="flex-1">
        <textarea name="description" rows="2" maxlength="500"
                  class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400 resize-none">{{ $val('description') }}</textarea>
    </div>
</div>

<div class="flex items-start gap-4 py-3 border-b border-gray-100">
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-2">Price Inclusive</label>
    <div class="flex-1 pt-1.5">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="include_base_amount" value="0">
            <input type="checkbox" name="include_base_amount" value="1"
                   @checked((bool) old('include_base_amount', $tax?->include_base_amount ?? false))
                   class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
            <span class="text-sm text-gray-600">Tax is included in the price (extract from gross)</span>
        </label>
    </div>
</div>

<div class="flex items-start gap-4 py-3">
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-2">Active</label>
    <div class="flex-1 pt-1.5">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1"
                   @checked((bool) old('active', $tax?->active ?? true))
                   class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
            <span class="text-sm text-gray-600">Active</span>
        </label>
    </div>
</div>
