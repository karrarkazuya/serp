@php
    $val = fn($field, $default = '') => old($field, $journal?->{$field} ?? $default);
@endphp

@if($errors->any())
<div class="px-6 pt-4 pb-0">
    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
        <p class="text-sm font-medium text-red-700 mb-1">Please fix the errors below.</p>
        <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
</div>
@endif

<div class="p-6">
    <div class="mb-6">
        <input type="text" name="name" value="{{ $val('name') }}" required placeholder="Journal name"
               class="w-full text-3xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 focus:outline-none focus:border-purple-500 pb-1 bg-transparent {{ $errors->has('name') ? 'border-red-400' : 'border-gray-200' }}">
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12">
        <div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Short Code</label>
                <input type="text" name="code" value="{{ $val('code') }}" required maxlength="16" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5" placeholder="INV">
            </div>

            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Type</label>
                <select name="type" required class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
                    <option value="">Select type…</option>
                    @foreach($journalTypes as $key => $label)
                        <option value="{{ $key }}" @selected($val('type') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Currency</label>
                <input type="text" name="currency" value="{{ $val('currency') }}" maxlength="10" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5" placeholder="USD">
            </div>

            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Company</label>
                <x-relation-dropdown
                    table="companies"
                    field="name"
                    name="company_id"
                    relation="many2one"
                    :compact="true"
                    :selected="old('company_id', $journal?->company_id ?? ($defaultCompanyId ?? null))"
                    class="flex-1"
                />
            </div>

            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Active</label>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" @checked($val('active', true)) class="rounded text-purple-600 focus:ring-purple-500">
                    <span class="text-sm text-gray-700">Yes</span>
                </label>
            </div>
        </div>

        <div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Default Account</label>
                <x-relation-dropdown
                    table="accounts"
                    field="name"
                    name="default_account_id"
                    relation="many2one"
                    :compact="true"
                    :selected="old('default_account_id', $journal?->default_account_id)"
                    class="flex-1"
                />
            </div>

            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Suspense Account</label>
                <x-relation-dropdown
                    table="accounts"
                    field="name"
                    name="suspense_account_id"
                    relation="many2one"
                    :compact="true"
                    :selected="old('suspense_account_id', $journal?->suspense_account_id)"
                    class="flex-1"
                />
            </div>

            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Sequence Prefix</label>
                <input type="text" name="sequence_prefix" value="{{ $val('sequence_prefix') }}" maxlength="32" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5" placeholder="INV/">
            </div>

            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Sequence Padding</label>
                <input type="number" name="sequence_padding" value="{{ $val('sequence_padding', 4) }}" min="1" max="10" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
            </div>

            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Next Number</label>
                <input type="number" name="sequence_next_number" value="{{ $val('sequence_next_number', 1) }}" min="1" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
            </div>
        </div>
    </div>
</div>
