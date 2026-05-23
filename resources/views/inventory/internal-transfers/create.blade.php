@extends('layouts.app')
@section('title', __('inventory.new_internal_transfer'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <form method="POST" action="{{ route('inventory.transfers.store') }}" class="flex flex-col h-full">
        @csrf
        <x-toolbar>
            <x-slot:breadcrumb>
                <a href="{{ route('inventory.internal-transfers.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('inventory.internal_transfers') }}</a>
                <span class="text-sm font-semibold text-gray-800">{{ __('inventory.new') }}</span>
            </x-slot:breadcrumb>
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.internal-transfers.index') }}" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">{{ __('inventory.discard') }}</a>
                    <button type="submit" class="px-3 py-1.5 text-sm font-semibold text-white bg-[#714B67] hover:bg-[#5c3d55] rounded">{{ __('inventory.save') }}</button>
                </div>
            </x-slot:actions>
        </x-toolbar>

        <div class="flex-1 overflow-y-auto">
            <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm p-6">
                @if($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                    <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
                @endif

                <div class="grid grid-cols-2 gap-x-8">
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.operation_type') }}</label>
                            <x-relation-dropdown table="inventory_operation_types" field="name" name="operation_type_id" relation="many2one"
                                :selected="old('operation_type_id', $defaultOperationTypeId ?? null)" class="flex-1" compact />
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.from') }}</label>
                            <x-relation-dropdown table="inventory_locations" field="complete_name" name="location_src_id" relation="many2one"
                                :selected="old('location_src_id')" class="flex-1" compact />
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.to') }}</label>
                            <x-relation-dropdown table="inventory_locations" field="complete_name" name="location_dest_id" relation="many2one"
                                :selected="old('location_dest_id')" class="flex-1" compact />
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.scheduled_date') }}</label>
                            <input type="datetime-local" name="scheduled_date" value="{{ old('scheduled_date', now()->format('Y-m-d\TH:i')) }}"
                                   class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.source_document') }}</label>
                            <input type="text" name="origin" value="{{ old('origin') }}" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5" placeholder="-">
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.company') }}</label>
                            <x-relation-dropdown table="companies" field="name" name="company_id" relation="many2one"
                                :selected="old('company_id', $defaultCompanyId ?? null)" class="flex-1" compact />
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="flex items-start gap-4 py-2 border-b border-gray-100">
                        <label class="w-40 shrink-0 text-sm text-gray-500 pt-0.5">{{ __('inventory.note') }}</label>
                        <textarea name="note" rows="2" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 resize-none" placeholder="-">{{ old('note') }}</textarea>
                    </div>
                </div>

                {{-- Moves --}}
                <div class="mt-6">
                    <div class="mb-2 text-sm font-semibold text-gray-700">{{ __('inventory.section_products') }}</div>
                    <table class="w-full text-sm mb-3">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="py-1.5 text-start text-xs text-gray-500">{{ __('inventory.product') }}</th>
                                <th class="py-1.5 text-start text-xs text-gray-500 w-32">{{ __('inventory.unit_of_measure') }}</th>
                                <th class="py-1.5 text-end text-xs text-gray-500 w-24">{{ __('inventory.quantity') }}</th>
                                <th class="w-8"></th>
                            </tr>
                        </thead>
                        <tbody x-data="{ nextIdx: {{ count(old('moves', [])) }}, newMoveRowUrl: @js(route('inventory.transfers.new-move-row')) }">
                            @foreach(old('moves', []) as $oldIdx => $oldMove)
                            @php $oldUomName = \App\Models\Inventory\Uom::find($oldMove['uom_id'] ?? null)?->name ?? '' @endphp
                            <tr x-data="{
                                uomId: {{ $oldMove['uom_id'] ?? 'null' }},
                                uomName: @js($oldUomName),
                                uomInfoUrl: @js(route('inventory.products.uom-info')),
                                onProductSelected(e) {
                                    const pid = e.detail.value; if (!pid) return;
                                    fetch(this.uomInfoUrl + '?product_id=' + pid, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                                        .then(r => r.json()).then(d => { this.uomId = d.uom_id; this.uomName = d.uom_name; });
                                }
                            }" @product-selected-{{ $oldIdx }}.window="onProductSelected($event)" class="border-b border-gray-50">
                                <td class="py-1.5">
                                    <x-relation-dropdown table="inventory_products" field="name" :name="'moves[' . $oldIdx . '][product_id]'"
                                        relation="many2one" :selected="$oldMove['product_id'] ?? null" :event="'product-selected-' . $oldIdx" compact />
                                </td>
                                <td class="py-1.5 w-32">
                                    <input type="hidden" name="moves[{{ $oldIdx }}][uom_id]" :value="uomId">
                                    <span x-text="uomName || '-'" class="text-sm text-gray-600"></span>
                                </td>
                                <td class="py-1.5 w-24">
                                    <input type="number" name="moves[{{ $oldIdx }}][product_qty]" value="{{ $oldMove['product_qty'] ?? 1 }}"
                                        step="0.001" min="0.001" class="w-full text-sm bg-transparent border-0 focus:outline-none px-0 text-end">
                                </td>
                                <td class="py-1.5 text-center w-8">
                                    <button type="button" onclick="this.closest('tr').remove()" class="text-gray-300 hover:text-red-500">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </td>
                            </tr>
                            @endforeach

                            <tr>
                                <td colspan="4" class="pt-2">
                                    <button type="button" class="text-xs font-medium text-purple-600 hover:text-purple-700"
                                        @click="fetch(newMoveRowUrl + '?idx=' + nextIdx, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                                            .then(r => r.text())
                                            .then(html => {
                                                const addRow = $el.closest('tr');
                                                addRow.insertAdjacentHTML('beforebegin', html);
                                                Alpine.initTree(addRow.previousElementSibling);
                                                nextIdx++;
                                            })">+ {{ __('inventory.add_a_product') }}</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
