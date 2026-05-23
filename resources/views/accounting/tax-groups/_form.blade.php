@php $isEdit = isset($taxGroup); @endphp
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 max-w-2xl">
    @if(!$isEdit)
    <div class="flex items-start gap-4 py-3 border-b border-gray-100">
        <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">Company <span class="text-red-500">*</span></label>
        <div class="flex-1">
            <x-relation-dropdown
                name="company_id"
                table="companies"
                field="name"
                :value="old('company_id', $defaultCompanyId ?? '')"
                placeholder="Select company…"
                required />
            @error('company_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>
    @endif

    <div class="flex items-start gap-4 py-3 border-b border-gray-100">
        <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">Name <span class="text-red-500">*</span></label>
        <div class="flex-1">
            <input type="text" name="name" value="{{ old('name', $taxGroup->name ?? '') }}"
                   class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400"
                   placeholder="e.g. VAT" required>
            @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="flex items-start gap-4 py-3 border-b border-gray-100">
        <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">Sequence</label>
        <div class="flex-1">
            <input type="number" name="sequence" value="{{ old('sequence', $taxGroup->sequence ?? 0) }}" min="0"
                   class="w-32 border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
        </div>
    </div>
</div>
