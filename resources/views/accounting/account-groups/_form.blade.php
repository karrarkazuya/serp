@php $isEdit = isset($accountGroup); @endphp
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 max-w-2xl">
    @if(!$isEdit)
    <div class="flex items-start gap-4 py-3 border-b border-gray-100">
        <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">{{ __('accounting.field_company') }} <span class="text-red-500">*</span></label>
        <div class="flex-1">
            <x-relation-dropdown
                name="company_id"
                table="companies"
                field="name"
                :value="old('company_id', $defaultCompanyId ?? '')"
                placeholder="{{ __('accounting.ph_select_company') }}"
                required />
            @error('company_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>
    @endif

    <div class="flex items-start gap-4 py-3 border-b border-gray-100">
        <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">{{ __('accounting.field_name') }} <span class="text-red-500">*</span></label>
        <div class="flex-1">
            <input type="text" name="name" value="{{ old('name', $accountGroup->name ?? '') }}"
                   class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400"
                   placeholder="{{ __('accounting.field_name') }}" required>
            @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="flex items-start gap-4 py-3 border-b border-gray-100">
        <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">{{ __('accounting.field_code_start') }}</label>
        <div class="flex-1 flex gap-3">
            <input type="text" name="code_prefix_start" value="{{ old('code_prefix_start', $accountGroup->code_prefix_start ?? '') }}"
                   class="w-32 border border-gray-300 rounded px-3 py-1.5 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-purple-400"
                   placeholder="{{ __('accounting.ph_eg_1000') }}">
            <span class="text-sm text-gray-400 pt-1.5">–</span>
            <input type="text" name="code_prefix_end" value="{{ old('code_prefix_end', $accountGroup->code_prefix_end ?? '') }}"
                   class="w-32 border border-gray-300 rounded px-3 py-1.5 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-purple-400"
                   placeholder="e.g. 1999">
        </div>
    </div>

    <div class="flex items-start gap-4 py-3 border-b border-gray-100">
        <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">{{ __('accounting.field_parent_group') }}</label>
        <div class="flex-1">
            <x-relation-dropdown
                name="parent_id"
                table="accounting_account_groups"
                field="name"
                :value="old('parent_id', $accountGroup->parent_id ?? '')"
                placeholder="{{ __('common.none') }}" />
        </div>
    </div>
</div>
