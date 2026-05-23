@php $val = fn($f, $d = '') => old($f, $location?->{$f} ?? $d); @endphp

@if($errors->any())
<div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
    <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="mb-5">
    <input type="text" name="name" value="{{ $val('name') }}" required placeholder="Location Name"
           class="w-full text-2xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 focus:outline-none focus:border-purple-500 pb-1 bg-transparent {{ $errors->has('name') ? 'border-red-400' : 'border-gray-200' }}">
</div>

<div class="grid grid-cols-2 gap-x-8">
    <div>
        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
            <label class="w-40 shrink-0 text-sm text-gray-500">Parent Location</label>
            <x-relation-dropdown table="inventory_locations" field="complete_name" name="parent_id" relation="many2one"
                :selected="old('parent_id', $location?->parent_id)" class="flex-1" compact />
        </div>
        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
            <label class="w-40 shrink-0 text-sm text-gray-500">Location Type</label>
            <select name="usage" class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                @foreach(['internal' => 'Internal Location', 'input' => 'Input Zone', 'output' => 'Output Zone', 'production' => 'Production', 'transit' => 'Transit', 'view' => 'View', 'supplier' => 'Vendor', 'customer' => 'Customer', 'inventory' => 'Inventory Adjustment', 'scrap' => 'Scrap'] as $k => $v)
                <option value="{{ $k }}" {{ $val('usage', 'internal') === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
            <label class="w-40 shrink-0 text-sm text-gray-500">Company</label>
            <x-relation-dropdown table="companies" field="name" name="company_id" relation="many2one"
                :selected="old('company_id', $location?->company_id ?? ($defaultCompanyId ?? null))" class="flex-1" compact />
        </div>
    </div>
    <div>
        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
            <label class="w-40 shrink-0 text-sm text-gray-500">Is Scrap Location?</label>
            <label class="flex items-center gap-2 text-sm text-gray-800">
                <input type="checkbox" name="scrap_location" value="1" {{ $val('scrap_location') ? 'checked' : '' }} class="rounded text-purple-600">
                Yes
            </label>
        </div>
        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
            <label class="w-40 shrink-0 text-sm text-gray-500">Is Return Location?</label>
            <label class="flex items-center gap-2 text-sm text-gray-800">
                <input type="checkbox" name="return_location" value="1" {{ $val('return_location') ? 'checked' : '' }} class="rounded text-purple-600">
                Yes
            </label>
        </div>
        <div class="flex items-start gap-4 py-2 border-b border-gray-100">
            <label class="w-40 shrink-0 text-sm text-gray-500 pt-0.5">Notes</label>
            <textarea name="notes" rows="2" class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 resize-none" placeholder="-">{{ $val('notes') }}</textarea>
        </div>
    </div>
</div>
