<div class="p-6 space-y-5">
    @if ($errors->any())
        <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.job_name') }} <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $job?->name) }}"
                   class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent"
                   required>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('common.department') }}</label>
            <x-relation-dropdown
                name="department_id"
                table="hr_departments"
                field="name"
                :selected="old('department_id', $job?->department_id)"
                placeholder="{{ __('employees.select_department') }}"
                compact
            />
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('common.company') }}</label>
            <x-relation-dropdown
                name="company_id"
                table="companies"
                field="name"
                :selected="old('company_id', $job?->company_id)"
                placeholder="{{ __('common.select_company') }}"
                compact
            />
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.expected_employees') }}</label>
            <input type="number" name="expected_employees" value="{{ old('expected_employees', $job?->expected_employees ?? 1) }}" min="0"
                   class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent">
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.recruitment_status') }}</label>
            <select name="state" class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent">
                <option value="normal" {{ old('state', $job?->state) === 'normal' ? 'selected' : '' }}>{{ __('employees.not_recruiting') }}</option>
                <option value="open"   {{ old('state', $job?->state) === 'open'   ? 'selected' : '' }}>{{ __('employees.recruiting') }}</option>
            </select>
        </div>

        <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.job_description') }}</label>
            <textarea name="description" rows="4"
                      class="w-full border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none p-2 text-sm bg-transparent resize-none">{{ old('description', $job?->description) }}</textarea>
        </div>

        <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.requirements') }}</label>
            <textarea name="requirements" rows="3"
                      class="w-full border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none p-2 text-sm bg-transparent resize-none">{{ old('requirements', $job?->requirements) }}</textarea>
        </div>
    </div>
</div>
