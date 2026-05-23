@php $val = fn($f, $d = '') => old($f, $productCategory?->{$f} ?? $d); @endphp

@if($errors->any())
<div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
    <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="mb-5">
    <input type="text" name="name" value="{{ $val('name') }}" required placeholder="Category Name"
           class="w-full text-2xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 focus:outline-none focus:border-purple-500 pb-1 bg-transparent {{ $errors->has('name') ? 'border-red-400' : 'border-gray-200' }}">
</div>

<div class="flex items-center gap-4 py-2 border-b border-gray-100">
    <label class="w-40 shrink-0 text-sm text-gray-500">Parent Category</label>
    <x-relation-dropdown table="inventory_product_categories" field="complete_name" name="parent_id" relation="many2one"
        :selected="old('parent_id', $productCategory?->parent_id)" :exclude="$productCategory?->id" class="flex-1" compact />
</div>

<div class="flex items-center gap-4 py-2 border-b border-gray-100">
    <label class="w-40 shrink-0 text-sm text-gray-500">Removal Strategy</label>
    <select name="removal_strategy" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
        @foreach(['fifo' => 'First In First Out (FIFO)', 'lifo' => 'Last In First Out (LIFO)', 'fefo' => 'First Expiry First Out (FEFO)', 'closest_location' => 'Closest Location'] as $k => $v)
        <option value="{{ $k }}" {{ $val('removal_strategy', 'fifo') === $k ? 'selected' : '' }}>{{ $v }}</option>
        @endforeach
    </select>
</div>

<div class="flex items-center gap-4 py-2 border-b border-gray-100">
    <label class="w-40 shrink-0 text-sm text-gray-500">Costing Method</label>
    <select name="costing_method" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
        @foreach(['standard_price' => 'Standard Price', 'average_cost' => 'Average Cost (AVCO)', 'fifo' => 'First In First Out (FIFO)'] as $k => $v)
        <option value="{{ $k }}" {{ $val('costing_method', 'standard_price') === $k ? 'selected' : '' }}>{{ $v }}</option>
        @endforeach
    </select>
</div>
