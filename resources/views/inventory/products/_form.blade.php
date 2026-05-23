@php
    $val = fn($field, $default = '') => old($field, $product?->{$field} ?? $default);
    $selectedRoutes = old('routes', $product ? $product->routes->pluck('id')->toArray() : []);
    $suppliers = old('suppliers', $product ? $product->suppliers->map(fn($s) => [
        'partner_id' => $s->partner_id, 'partner_name' => $s->partner?->name ?? '',
        'price' => $s->price, 'min_qty' => $s->min_qty, 'delay' => $s->delay,
    ])->toArray() : [['partner_id' => '', 'partner_name' => '', 'price' => '', 'min_qty' => 0, 'lead_time' => 0]]);
@endphp

@if($errors->any())
<div class="px-6 pt-4 pb-0">
    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
        <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
</div>
@endif

<div class="p-6" x-data="{
    imagePreview: '{{ $product && $product->image_uuid ? route('files.serve', $product->image_uuid) : '' }}',
    suppliers: {{ Js::from($suppliers) }},
    addSupplier() { this.suppliers.push({ partner_id: '', partner_name: '', price: '', min_qty: 0, delay: 0 }); },
    removeSupplier(i) { this.suppliers.splice(i, 1); }
}">
    {{-- Title --}}
    <div class="mb-5">
        <input type="text" name="name" value="{{ $val('name') }}" required placeholder="Product Name"
               class="w-full text-2xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 focus:outline-none focus:border-purple-500 pb-1 bg-transparent {{ $errors->has('name') ? 'border-red-400' : 'border-gray-200' }}">
    </div>

    <div class="flex gap-8">
        {{-- Left column --}}
        <div class="flex-1 space-y-0">
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Reference</label>
                <input type="text" name="internal_reference" value="{{ $val('internal_reference') }}" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5" placeholder="-">
            </div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Barcode</label>
                <input type="text" name="barcode" value="{{ $val('barcode') }}" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5" placeholder="-">
            </div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Product Type</label>
                <select name="product_type" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
                    @foreach(['storable' => 'Storable Product', 'consumable' => 'Consumable', 'service' => 'Service'] as $k => $v)
                    <option value="{{ $k }}" {{ $val('product_type', 'storable') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Category</label>
                <x-relation-dropdown table="inventory_product_categories" field="complete_name" name="category_id" relation="many2one"
                    :selected="old('category_id', $product?->category_id)" class="flex-1" compact />
            </div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Unit of Measure</label>
                <x-relation-dropdown table="inventory_uoms" field="name" name="uom_id" relation="many2one"
                    :selected="old('uom_id', $product?->uom_id)" class="flex-1" compact />
            </div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Purchase UoM</label>
                <x-relation-dropdown table="inventory_uoms" field="name" name="uom_po_id" relation="many2one"
                    :selected="old('uom_po_id', $product?->uom_po_id)" class="flex-1" compact />
            </div>
        </div>

        {{-- Right column --}}
        <div class="flex-1 space-y-0">
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Sales Price</label>
                <input type="number" name="sale_price" value="{{ $val('sale_price', 0) }}" step="0.01" min="0" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
            </div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Cost</label>
                <input type="number" name="cost" value="{{ $val('cost', 0) }}" step="0.01" min="0" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
            </div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Tracking</label>
                <select name="tracking" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
                    @foreach(['none' => 'No Tracking', 'lot' => 'By Lot', 'serial' => 'By Unique Serial Number'] as $k => $v)
                    <option value="{{ $k }}" {{ $val('tracking', 'none') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Weight (kg)</label>
                <input type="number" name="weight" value="{{ $val('weight', 0) }}" step="0.001" min="0" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
            </div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">Volume (m³)</label>
                <input type="number" name="volume" value="{{ $val('volume', 0) }}" step="0.001" min="0" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
            </div>
        </div>

        {{-- Image --}}
        <div class="shrink-0 w-36">
            <label class="block cursor-pointer">
                <div class="w-36 h-36 rounded-xl overflow-hidden border border-gray-200 shadow-sm bg-gray-50 flex items-center justify-center">
                    <img x-show="imagePreview" :src="imagePreview" class="w-full h-full object-cover" style="display:none">
                    <div x-show="!imagePreview" class="text-4xl font-bold text-gray-300">?</div>
                </div>
                <input type="file" name="image" accept="image/*" class="hidden" @change="imagePreview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : imagePreview">
                <p class="mt-1.5 text-center text-xs text-gray-400">Click to upload</p>
            </label>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mt-8 border-t border-gray-200" x-data="{ tab: 'inventory' }">
        <div class="flex items-end gap-1 pt-3 border-b border-gray-200">
            @foreach(['inventory' => 'Inventory', 'purchase' => 'Purchase', 'description' => 'Description'] as $key => $label)
            <button type="button" @click="tab = '{{ $key }}'"
                    class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white"
                    :class="tab === '{{ $key }}' ? 'text-gray-900 border-gray-300 -mb-px pb-[9px]' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                {{ $label }}
            </button>
            @endforeach
        </div>

        <div class="min-h-48 pt-4">
            <div x-show="tab === 'inventory'" style="display:none">
                <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                    <label class="w-40 shrink-0 text-sm text-gray-500">Routes</label>
                    <x-relation-dropdown table="inventory_routes" field="name" name="routes"
                        relation="many2many" :selected="$selectedRoutes" class="flex-1" compact />
                </div>
                <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                    <label class="w-40 shrink-0 text-sm text-gray-500">Expiration Date</label>
                    <label class="flex items-center gap-2 text-sm text-gray-800">
                        <input type="checkbox" name="has_expiration_date" value="1" {{ $val('has_expiration_date') ? 'checked' : '' }} class="rounded text-purple-600">
                        Track expiration dates
                    </label>
                </div>
            </div>

            <div x-show="tab === 'purchase'" style="display:none">
                <div class="mb-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Vendors / Suppliers</div>
                <table class="w-full text-sm mb-3">
                    <thead><tr class="border-b border-gray-100">
                        <th class="py-1.5 text-left text-xs text-gray-500 font-medium">Vendor</th>
                        <th class="py-1.5 text-right text-xs text-gray-500 font-medium w-24">Price</th>
                        <th class="py-1.5 text-right text-xs text-gray-500 font-medium w-20">Min Qty</th>
                        <th class="py-1.5 text-right text-xs text-gray-500 font-medium w-20">Lead Time</th>
                        <th class="w-8"></th>
                    </tr></thead>
                    <tbody>
                        <template x-for="(s, i) in suppliers" :key="i">
                            <tr class="border-b border-gray-50">
                                <td class="py-1.5">
                                    <input type="hidden" :name="'suppliers['+i+'][partner_id]'" :value="s.partner_id">
                                    <input type="text" :name="'suppliers['+i+'][partner_name]'" x-model="s.partner_name" placeholder="Vendor name" class="w-full text-sm bg-transparent border-0 focus:outline-none focus:ring-0 px-0">
                                </td>
                                <td class="py-1.5"><input type="number" :name="'suppliers['+i+'][price]'" x-model="s.price" step="0.01" min="0" class="w-full text-sm bg-transparent border-0 focus:outline-none focus:ring-0 px-0 text-right"></td>
                                <td class="py-1.5"><input type="number" :name="'suppliers['+i+'][min_qty]'" x-model="s.min_qty" min="0" class="w-full text-sm bg-transparent border-0 focus:outline-none focus:ring-0 px-0 text-right"></td>
                                <td class="py-1.5"><input type="number" :name="'suppliers['+i+'][delay]'" x-model="s.delay" min="0" class="w-full text-sm bg-transparent border-0 focus:outline-none focus:ring-0 px-0 text-right"></td>
                                <td class="py-1.5 text-center"><button type="button" @click="removeSupplier(i)" class="text-gray-300 hover:text-red-500"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <button type="button" @click="addSupplier()" class="text-xs font-medium text-purple-600 hover:text-purple-700">+ Add a vendor</button>
            </div>

            <div x-show="tab === 'description'" style="display:none">
                <textarea name="description" rows="6" placeholder="Product description..."
                    class="w-full text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 resize-y px-0">{{ $val('description') }}</textarea>
                <div class="flex items-center gap-4 py-2 border-b border-gray-100 mt-2">
                    <label class="w-40 shrink-0 text-sm text-gray-500">Picking Description</label>
                    <input type="text" name="description_picking" value="{{ $val('description_picking') }}" class="flex-1 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 px-0">
                </div>
            </div>
        </div>
    </div>
</div>
