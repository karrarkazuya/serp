@php $val = fn($f, $d = '') => old($f, $lot?->{$f} ?? $d); @endphp

@if($errors->any())
<div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
    <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="mb-5">
    <input type="text" name="name" value="{{ $val('name') }}" required placeholder="{{ __('inventory.col_lot') }}"
           class="w-full text-2xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 focus:outline-none focus:border-purple-500 pb-1 bg-transparent {{ $errors->has('name') ? 'border-red-400' : 'border-gray-200' }}">
</div>

<div class="grid grid-cols-2 gap-x-8">
    <div>
        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.product') }}</label>
            <x-relation-dropdown table="inventory_products" field="name" name="product_id" relation="many2one"
                :selected="old('product_id', $lot?->product_id)" class="flex-1" compact />
        </div>
        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.company') }}</label>
            <x-relation-dropdown table="companies" field="name" name="company_id" relation="many2one"
                :selected="old('company_id', $lot?->company_id ?? ($defaultCompanyId ?? null))" class="flex-1" compact />
        </div>
    </div>
    <div>
        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.expiration_date') }}</label>
            <input type="date" name="expiration_date" value="{{ $val('expiration_date') }}" class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
        </div>
        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.best_before_date') }}</label>
            <input type="date" name="use_date" value="{{ $val('use_date') }}" class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
        </div>
    </div>
</div>

<div class="flex items-start gap-4 py-2 border-b border-gray-100 mt-2">
    <label class="w-40 shrink-0 text-sm text-gray-500 pt-0.5">{{ __('inventory.description') }}</label>
    <textarea name="description" rows="3" class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 resize-none" placeholder="-">{{ $val('description') }}</textarea>
</div>
