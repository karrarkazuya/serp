@php
    /** @var \App\Models\Employees\RequestSubtype|null $subtype */
    $subtype ??= null;
    $defaultCompanyId ??= null;
    $val = fn ($key, $default = null) => old($key, $subtype?->{$key} ?? $default);
@endphp

<div x-data="{ type: @js($val('type', 'leave')) }" class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
    <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.subtype_name') }} <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ $val('name') }}" required
               class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent">
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.subtype_type') }} <span class="text-red-500">*</span></label>
        <select name="type" x-model="type" class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent">
            <option value="leave">{{ __('employees.leave') }}</option>
            <option value="time_off">{{ __('employees.time_off') }}</option>
            <option value="overtime">{{ __('employees.overtime') }}</option>
        </select>
    </div>

    <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('common.company') }}</label>
        <x-relation-dropdown name="company_id" table="companies" field="name" :selected="$val('company_id', $defaultCompanyId)" placeholder="{{ __('common.select_company') }}" compact />
    </div>
    <div x-show="type === 'overtime'" style="display:none">
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.subtype_factor') }}</label>
        <input type="number" step="0.01" min="0.01" max="99.99" name="factor" value="{{ $val('factor', 1.0) }}"
               class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent">
        <p class="text-[11px] text-gray-400 mt-1">{{ __('employees.subtype_factor_hint') }}</p>
    </div>

    <div class="sm:col-span-2 grid grid-cols-2 sm:grid-cols-3 gap-3 pt-2">
        @php
            $checks = [
                'cuts_salary'          => __('employees.subtype_cuts_salary'),
                'cuts_balance'         => __('employees.subtype_cuts_balance'),
                'requires_title'       => __('employees.subtype_requires_title'),
                'requires_description' => __('employees.subtype_requires_description'),
                'requires_attachment'  => __('employees.subtype_requires_attachment'),
                'active'               => __('common.active'),
            ];
        @endphp
        @foreach($checks as $name => $label)
        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="hidden" name="{{ $name }}" value="0">
            <input type="checkbox" name="{{ $name }}" value="1"
                   {{ ($name === 'requires_title' || $name === 'active') ? ($val($name, 1) ? 'checked' : '') : ($val($name) ? 'checked' : '') }}
                   class="rounded border-gray-300 text-purple-600">
            {{ $label }}
        </label>
        @endforeach
    </div>
</div>
