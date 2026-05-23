<tr x-data="{
    uomId: null,
    uomName: '',
    uomInfoUrl: @js(route('inventory.products.uom-info')),
    onProductSelected(e) {
        const pid = e.detail.value;
        if (!pid) return;
        fetch(this.uomInfoUrl + '?product_id=' + pid, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(r => r.json()).then(d => {
            this.uomId = d.uom_id;
            this.uomName = d.uom_name;
        });
    }
}" @product-selected-{{ $idx }}.window="onProductSelected($event)" class="border-b border-gray-50">
    <td class="py-1.5">
        <x-relation-dropdown
            table="inventory_products"
            field="name"
            :name="'moves[' . $idx . '][product_id]'"
            relation="many2one"
            :event="'product-selected-' . $idx"
            compact />
    </td>
    <td class="py-1.5 w-32">
        <input type="hidden" name="moves[{{ $idx }}][uom_id]" :value="uomId">
        <span x-text="uomName || '-'" class="text-sm text-gray-600"></span>
    </td>
    <td class="py-1.5 w-24">
        <input type="number" name="moves[{{ $idx }}][product_qty]" value="1"
            step="0.001" min="0.001"
            class="w-full text-sm bg-transparent border-0 focus:outline-none px-0 text-right">
    </td>
    <td class="py-1.5 text-center w-8">
        <button type="button" onclick="this.closest('tr').remove()" class="text-gray-300 hover:text-red-500">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </td>
</tr>
