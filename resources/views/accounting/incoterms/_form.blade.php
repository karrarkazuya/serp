@php $isEdit = isset($incoterm); @endphp
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 max-w-2xl">
    <div class="flex items-start gap-4 py-3 border-b border-gray-100">
        <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">{{ __('accounting.field_code') }} <span class="text-red-500">*</span></label>
        <div class="flex-1">
            <input type="text" name="code" value="{{ old('code', $incoterm->code ?? '') }}"
                   class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm font-mono uppercase focus:outline-none focus:ring-1 focus:ring-purple-400"
                   placeholder="e.g. FOB" maxlength="10" required>
            @error('code')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="flex items-start gap-4 py-3 border-b border-gray-100">
        <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">{{ __('accounting.field_name') }} <span class="text-red-500">*</span></label>
        <div class="flex-1">
            <input type="text" name="name" value="{{ old('name', $incoterm->name ?? '') }}"
                   class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400"
                   placeholder="e.g. Free on Board" required>
            @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
