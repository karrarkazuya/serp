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
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Department Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $department?->name) }}"
                   class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent"
                   required>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Manager</label>
            <x-relation-dropdown
                name="manager_id"
                table="hr_employees"
                :selected-id="old('manager_id', $department?->manager_id)"
                :selected-label="$department?->manager?->name"
                placeholder="Select manager..."
            />
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Parent Department</label>
            <x-relation-dropdown
                name="parent_id"
                table="hr_departments"
                :selected-id="old('parent_id', $department?->parent_id)"
                :selected-label="$department?->parent?->name"
                placeholder="Select parent department..."
            />
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Company</label>
            <x-relation-dropdown
                name="company_id"
                table="companies"
                :selected-id="old('company_id', $department?->company_id)"
                :selected-label="$department?->company?->name"
                placeholder="Select company..."
            />
        </div>

        <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Notes</label>
            <textarea name="note" rows="3"
                      class="w-full border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none p-2 text-sm bg-transparent resize-none">{{ old('note', $department?->note) }}</textarea>
        </div>
    </div>
</div>
