@extends('layouts.app')
@section('title', 'Edit: ' . $picking->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <form method="POST" action="{{ route('inventory.transfers.update', $picking) }}" class="flex flex-col h-full">
        @csrf @method('PUT')
        <x-toolbar>
            <x-slot:breadcrumb>
                @php
                    [$listRoute, $listLabel] = match($picking->operationType?->code) {
                        'incoming' => [route('inventory.receipts.index'), 'Receipts'],
                        'outgoing' => [route('inventory.deliveries.index'), 'Deliveries'],
                        'internal' => [route('inventory.internal-transfers.index'), 'Internal Transfers'],
                        default    => [route('inventory.transfers.index'), 'Transfers'],
                    };
                @endphp
                <a href="{{ $listRoute }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $listLabel }}</a>
                <a href="{{ route('inventory.transfers.show', $picking) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $picking->name }}</a>
                <span class="text-sm font-semibold text-gray-800">Edit</span>
            </x-slot:breadcrumb>
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.transfers.show', $picking) }}" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">Discard</a>
                    <button type="submit" class="px-3 py-1.5 text-sm font-semibold text-white bg-[#714B67] hover:bg-[#5c3d55] rounded">Save</button>
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

                <div class="grid grid-cols-2 gap-x-8 mb-6">
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">Source Location</label>
                            <x-relation-dropdown table="inventory_locations" field="complete_name" name="location_src_id" relation="many2one"
                                :selected="old('location_src_id', $picking->location_src_id)" class="flex-1" compact />
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">Destination</label>
                            <x-relation-dropdown table="inventory_locations" field="complete_name" name="location_dest_id" relation="many2one"
                                :selected="old('location_dest_id', $picking->location_dest_id)" class="flex-1" compact />
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">Scheduled Date</label>
                            <input type="datetime-local" name="scheduled_date" value="{{ old('scheduled_date', $picking->scheduled_date?->format('Y-m-d\TH:i')) }}"
                                   class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">Source Document</label>
                            <input type="text" name="origin" value="{{ old('origin', $picking->origin) }}" class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5" placeholder="-">
                        </div>
                    </div>
                </div>

                {{-- Moves --}}
                <div class="mb-2 text-sm font-semibold text-gray-700">Operations</div>
                <table class="w-full text-sm mb-3">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="py-1.5 text-left text-xs text-gray-500">Product</th>
                            <th class="py-1.5 text-left text-xs text-gray-500 w-32">Unit of Measure</th>
                            <th class="py-1.5 text-right text-xs text-gray-500 w-24">Quantity</th>
                            <th class="w-8"></th>
                        </tr>
                    </thead>

                    {{-- Existing moves rendered by Blade so each row can use <x-relation-dropdown> --}}
                    <tbody>
                        @foreach($picking->moves as $idx => $move)
                        <tr x-data="{
                                deleted: false,
                                uomId: {{ $move->uom_id ?? ($move->product?->uom_id ?? 'null') }},
                                uomName: '{{ addslashes($move->product?->uom?->name ?? '') }}',
                                uomInfoUrl: @js(route('inventory.products.uom-info')),
                                onProductChanged(e) {
                                    const pid = e.detail.value;
                                    if (!pid) return;
                                    fetch(this.uomInfoUrl + '?product_id=' + pid, {
                                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                                    }).then(r => r.json()).then(d => {
                                        this.uomId = d.uom_id;
                                        this.uomName = d.uom_name;
                                    });
                                }
                            }"
                            :class="deleted ? 'opacity-40' : ''"
                            @product-selected-{{ $idx }}.window="onProductChanged($event)"
                            class="border-b border-gray-50">
                            <td class="py-1.5">
                                <input type="hidden" name="moves[{{ $idx }}][id]" value="{{ $move->id }}">
                                <input type="hidden" name="moves[{{ $idx }}][delete]" :value="deleted ? 1 : 0">
                                <x-relation-dropdown
                                    table="inventory_products"
                                    field="name"
                                    :name="'moves[' . $idx . '][product_id]'"
                                    relation="many2one"
                                    :selected="old('moves.' . $idx . '.product_id', $move->product_id)"
                                    :event="'product-selected-' . $idx"
                                    compact />
                            </td>
                            <td class="py-1.5 w-32">
                                <input type="hidden" name="moves[{{ $idx }}][uom_id]" :value="uomId">
                                <span x-text="uomName || '-'" class="text-sm text-gray-600"></span>
                            </td>
                            <td class="py-1.5 w-24">
                                <input type="number"
                                    name="moves[{{ $idx }}][product_qty]"
                                    value="{{ old('moves.' . $idx . '.product_qty', $move->product_qty) }}"
                                    step="0.001" min="0.001" :disabled="deleted"
                                    class="w-full text-sm bg-transparent border-0 focus:outline-none px-0 text-right">
                            </td>
                            <td class="py-1.5 text-center w-8">
                                <button type="button" @click="deleted = !deleted"
                                    :class="deleted ? 'text-green-500' : 'text-gray-300 hover:text-red-500'">
                                    <template x-if="deleted"><span class="text-xs">Undo</span></template>
                                    <template x-if="!deleted">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </template>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>

                    {{-- New moves added client-side via server-rendered partial --}}
                    <tbody x-data="{ nextIdx: {{ $picking->moves->count() }}, newMoveRowUrl: @js(route('inventory.transfers.new-move-row')) }">
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
                                        })">+ Add a product</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>
@endsection
