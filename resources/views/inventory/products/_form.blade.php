@php
    $val = fn($field, $default = '') => old($field, $product?->{$field} ?? $default);
    $suppliers = old('suppliers', $product ? $product->suppliers->map(fn($s) => [
        'partner_id' => $s->partner_id, 'partner_name' => $s->partner?->name ?? '',
        'price' => $s->price, 'min_qty' => $s->min_qty, 'delay' => $s->delay,
        'partnerOpen' => false, 'partnerOptions' => [],
    ])->toArray() : [['partner_id' => '', 'partner_name' => '', 'price' => '', 'min_qty' => 0, 'delay' => 0, 'partnerOpen' => false, 'partnerOptions' => []]]);
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
    vendorUrl: @js(route('relation-dropdown.lookup', ['table' => 'contacts'])),
    async fetchVendors(s) {
        const res = await fetch(this.vendorUrl + '?field=name&search=' + encodeURIComponent(s.partner_name || ''), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        s.partnerOptions = (await res.json()).data || [];
    },
    selectVendor(s, opt) {
        s.partner_id = opt.id;
        s.partner_name = opt.label;
        s.partnerOpen = false;
    },
    addSupplier() { this.suppliers.push({ partner_id: '', partner_name: '', price: '', min_qty: 0, delay: 0, partnerOpen: false, partnerOptions: [] }); },
    removeSupplier(i) { this.suppliers.splice(i, 1); }
}">
    {{-- The previous "inert fields notice" banner pointed at half a dozen
         dead controls (weight, volume, purchase UoM, routes pivot). Those
         controls were hidden in the cleanup pass — the remaining fields
         (internal_reference, barcode) are searched and displayed, so a
         user-facing inert warning is no longer accurate. --}}

    {{-- Title --}}
    <div class="mb-5">
        <input type="text" name="name" value="{{ $val('name') }}" required placeholder="{{ __('inventory.name') }}"
               class="w-full text-2xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 focus:outline-none focus:border-purple-500 pb-1 bg-transparent {{ $errors->has('name') ? 'border-red-400' : 'border-gray-200' }}">
    </div>

    <div class="flex gap-8">
        {{-- Left column --}}
        <div class="flex-1 space-y-0">
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.internal_reference') }}</label>
                <input type="text" name="internal_reference" value="{{ $val('internal_reference') }}" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5" placeholder="-">
            </div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.barcode') }}</label>
                <input type="text" name="barcode" value="{{ $val('barcode') }}" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5" placeholder="-">
            </div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.product_type') }}</label>
                <select name="product_type" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
                    @foreach(['storable' => __('inventory.storable_product'), 'consumable' => __('inventory.consumable'), 'service' => __('inventory.service')] as $k => $v)
                    <option value="{{ $k }}" {{ $val('product_type', 'storable') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.category') }}</label>
                <x-relation-dropdown table="inventory_product_categories" field="complete_name" name="category_id" relation="many2one"
                    :selected="old('category_id', $product?->category_id)" class="flex-1" compact />
            </div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.unit_of_measure') }}</label>
                <x-relation-dropdown table="inventory_uoms" field="name" name="uom_id" relation="many2one"
                    :selected="old('uom_id', $product?->uom_id)" class="flex-1" compact />
            </div>
            {{-- `purchase_uom` (uom_po_id) was a separate UoM for PO documents;
                 no purchase-orders module reads it. Hidden until that ships.
                 Controller defaults uom_po_id = uom_id on save so the NOT
                 NULL column stays satisfied. --}}
        </div>

        {{-- Right column --}}
        <div class="flex-1 space-y-0">
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.sales_price') }}</label>
                <input type="number" name="sale_price" value="{{ $val('sale_price', 0) }}" step="0.01" min="0" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
            </div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.cost') }}</label>
                <input type="number" name="cost" value="{{ $val('cost', 0) }}" step="0.01" min="0" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
            </div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.tracking') }}</label>
                <select name="tracking" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
                    @foreach(['none' => __('inventory.no_tracking'), 'lot' => __('inventory.by_lot'), 'serial' => __('inventory.by_serial')] as $k => $v)
                    <option value="{{ $k }}" {{ $val('tracking', 'none') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            {{-- `weight` and `volume` were free-entry numbers — no shipping,
                 forecasting, or report path read them, so users typed in
                 dimensions and got no behaviour back. Hidden; columns kept
                 in the DB. --}}
        </div>

        {{-- Image --}}
        <div class="shrink-0 w-36">
            <label class="block cursor-pointer">
                <div class="w-36 h-36 rounded-xl overflow-hidden border border-gray-200 shadow-sm bg-gray-50 flex items-center justify-center">
                    <img x-show="imagePreview" :src="imagePreview" class="w-full h-full object-cover" style="display:none">
                    <div x-show="!imagePreview" class="text-4xl font-bold text-gray-300">?</div>
                </div>
                <input type="file" name="image" accept="image/*" class="hidden" @change="imagePreview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : imagePreview">
                <p class="mt-1.5 text-center text-xs text-gray-400">{{ __('inventory.click_to_upload') }}</p>
            </label>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mt-8 border-t border-gray-200" x-data="{ tab: 'inventory' }">
        <div class="flex items-end gap-1 pt-3 border-b border-gray-200">
            @foreach(['inventory' => __('inventory.section_inventory'), 'purchase' => __('inventory.section_purchase'), 'description' => __('inventory.section_description')] as $key => $label)
            <button type="button" @click="tab = '{{ $key }}'"
                    class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white"
                    :class="tab === '{{ $key }}' ? 'text-gray-900 border-gray-300 -mb-px pb-[9px]' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                {{ $label }}
            </button>
            @endforeach
        </div>

        <div class="min-h-48 pt-4">
            <div x-show="tab === 'inventory'" style="display:none">
                {{-- The `routes` many-to-many dropdown was rendered here. No
                     engine reads product-level routes (push chain fires off
                     the warehouse Route, not the Product's routes pivot), so
                     users picked routes and got no behaviour back. Hidden;
                     the inventory_product_routes pivot table is left empty
                     for new products and the existing rows render only on
                     the show page if any happen to have been attached. --}}
                <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                    <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.expiration_date') }}</label>
                    <label class="flex items-center gap-2 text-sm text-gray-800">
                        <input type="checkbox" name="has_expiration_date" value="1" {{ $val('has_expiration_date') ? 'checked' : '' }} class="rounded text-purple-600">
                        {{ __('inventory.track_expiration') }}
                    </label>
                </div>
            </div>

            <div x-show="tab === 'purchase'" style="display:none">
                <div class="mb-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('inventory.section_vendors') }}</div>
                <table class="w-full text-sm mb-3">
                    <thead><tr class="border-b border-gray-100">
                        <th class="py-1.5 text-start text-xs text-gray-500 font-medium">{{ __('inventory.vendor') }}</th>
                        <th class="py-1.5 text-end text-xs text-gray-500 font-medium w-24">{{ __('inventory.price') }}</th>
                        <th class="py-1.5 text-end text-xs text-gray-500 font-medium w-20">{{ __('inventory.min_qty') }}</th>
                        <th class="py-1.5 text-end text-xs text-gray-500 font-medium w-20">{{ __('inventory.lead_time') }}</th>
                        <th class="w-8"></th>
                    </tr></thead>
                    <tbody>
                        <template x-for="(s, i) in suppliers" :key="i">
                            <tr class="border-b border-gray-50">
                                <td class="py-1.5">
                                    <input type="hidden" :name="'suppliers['+i+'][partner_id]'" :value="s.partner_id">
                                    <div class="relative" @click.outside="s.partnerOpen = false">
                                        <input type="text" x-model="s.partner_name"
                                            @focus="s.partnerOpen = true; fetchVendors(s)"
                                            @input.debounce.250ms="fetchVendors(s)"
                                            placeholder="{{ __('inventory.vendor') }}..."
                                            class="w-full text-sm bg-transparent border-0 border-b border-dotted border-gray-300 focus:border-purple-500 focus:outline-none px-0 py-1">
                                        <div x-show="s.partnerOpen" style="display:none"
                                            class="absolute start-0 top-full z-40 w-full max-w-xs bg-white border border-gray-200 rounded-b-lg shadow-lg overflow-hidden">
                                            <div class="max-h-48 overflow-y-auto py-1">
                                                <template x-for="opt in s.partnerOptions" :key="opt.id">
                                                    <button type="button" @click="selectVendor(s, opt)"
                                                        class="w-full text-start px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                                        x-text="opt.label"></button>
                                                </template>
                                                <div x-show="s.partnerOptions.length === 0" class="px-4 py-2 text-sm text-gray-400">{{ __('inventory.no_results') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-1.5"><input type="number" :name="'suppliers['+i+'][price]'" x-model="s.price" step="0.01" min="0" class="w-full text-sm bg-transparent border-0 focus:outline-none focus:ring-0 px-0 text-end"></td>
                                <td class="py-1.5"><input type="number" :name="'suppliers['+i+'][min_qty]'" x-model="s.min_qty" min="0" class="w-full text-sm bg-transparent border-0 focus:outline-none focus:ring-0 px-0 text-end"></td>
                                <td class="py-1.5"><input type="number" :name="'suppliers['+i+'][delay]'" x-model="s.delay" min="0" class="w-full text-sm bg-transparent border-0 focus:outline-none focus:ring-0 px-0 text-end"></td>
                                <td class="py-1.5 text-center"><button type="button" @click="removeSupplier(i)" class="text-gray-300 hover:text-red-500"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <button type="button" @click="addSupplier()" class="text-xs font-medium text-purple-600 hover:text-purple-700">+ {{ __('inventory.add_a_vendor') }}</button>
            </div>

            <div x-show="tab === 'description'" style="display:none">
                <textarea name="description" rows="6" placeholder="{{ __('inventory.description') }}..."
                    class="w-full text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 resize-y px-0">{{ $val('description') }}</textarea>
                <div class="flex items-center gap-4 py-2 border-b border-gray-100 mt-2">
                    <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.picking_description') }}</label>
                    <input type="text" name="description_picking" value="{{ $val('description_picking') }}" class="flex-1 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 px-0">
                </div>
            </div>
        </div>
    </div>
</div>
