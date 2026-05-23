@php $val = fn($field, $default = '') => old($field, $tax?->{$field} ?? $default); @endphp

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
        <label class="w-40 shrink-0 text-sm font-semibold text-gray-700">Tax Name <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ $val('name') }}" required maxlength="255"
               class="flex-1 text-sm border-0 border-b border-gray-300 focus:border-purple-500 focus:outline-none focus:ring-0 px-0 py-1">
    </div>

    <div class="flex items-center gap-5">
        <label class="w-40 shrink-0 text-sm font-semibold text-gray-700">Tax Type <span class="text-red-500">*</span></label>
        <select name="amount_type" required
                class="flex-1 text-sm border-0 border-b border-gray-300 focus:border-purple-500 focus:outline-none focus:ring-0 px-0 py-1 bg-transparent">
            @foreach(\App\Models\Accounting\AccountTax::AMOUNT_TYPES as $key => $label)
            <option value="{{ $key }}" @selected($val('amount_type', 'percent') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex items-center gap-5">
        <label class="w-40 shrink-0 text-sm font-semibold text-gray-700">Amount <span class="text-red-500">*</span></label>
        <input type="number" name="amount" value="{{ $val('amount', '0') }}" step="0.0001" min="0" required
               class="flex-1 text-sm border-0 border-b border-gray-300 focus:border-purple-500 focus:outline-none focus:ring-0 px-0 py-1">
    </div>

    <div class="flex items-center gap-5">
        <label class="w-40 shrink-0 text-sm font-semibold text-gray-700">Applies To <span class="text-red-500">*</span></label>
        <select name="type_tax_use" required
                class="flex-1 text-sm border-0 border-b border-gray-300 focus:border-purple-500 focus:outline-none focus:ring-0 px-0 py-1 bg-transparent">
            @foreach(\App\Models\Accounting\AccountTax::TYPE_TAX_USE as $key => $label)
            <option value="{{ $key }}" @selected($val('type_tax_use', 'sale') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex items-center gap-5">
        <label class="w-40 shrink-0 text-sm font-semibold text-gray-700">Tax Account</label>
        <div class="flex-1">
            <x-relation-dropdown
                table="accounts"
                field="name"
                name="account_id"
                relation="many2one"
                :compact="true"
                :selected="old('account_id', $tax?->account_id)"
            />
        </div>
    </div>

    <div class="flex items-center gap-5">
        <label class="w-40 shrink-0 text-sm font-semibold text-gray-700">Description</label>
        <textarea name="description" rows="2" maxlength="500"
                  class="flex-1 text-sm border border-gray-200 rounded px-2 py-1 focus:border-purple-500 focus:outline-none focus:ring-0 resize-none">{{ $val('description') }}</textarea>
    </div>

    <div class="flex items-center gap-5">
        <label class="w-40 shrink-0 text-sm font-semibold text-gray-700">Price Inclusive</label>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="include_base_amount" value="0">
            <input type="checkbox" name="include_base_amount" value="1"
                   @checked((bool) old('include_base_amount', $tax?->include_base_amount ?? false))
                   class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
            <span class="text-sm text-gray-600">Tax is included in the price (extract from gross)</span>
        </label>
    </div>

    <div class="flex items-center gap-5">
        <label class="w-40 shrink-0 text-sm font-semibold text-gray-700">Active</label>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1"
                   @checked((bool) old('active', $tax?->active ?? true))
                   class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
            <span class="text-sm text-gray-600">Active</span>
        </label>
    </div>

    @if(!isset($tax))
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
