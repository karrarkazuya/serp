@extends('layouts.app')
@section('title', 'Edit: ' . $picking->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <form method="POST" action="{{ route('inventory.transfers.update', $picking) }}" class="flex flex-col h-full">
        @csrf @method('PUT')
        <x-toolbar>
            <x-slot:breadcrumb>
                <a href="{{ route('inventory.transfers.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Transfers</a>
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

                    {{-- Existing moves: Blade-rendered so each row can use <x-relation-dropdown> --}}
                    <tbody>
                        @foreach($picking->moves as $idx => $move)
                        <tr x-data="{ deleted: false }" :class="deleted ? 'opacity-40' : ''" class="border-b border-gray-50">
                            <td class="py-1.5">
                                <input type="hidden" name="moves[{{ $idx }}][id]" value="{{ $move->id }}">
                                <input type="hidden" name="moves[{{ $idx }}][delete]" :value="deleted ? 1 : 0">
                                <x-relation-dropdown
                                    table="inventory_products"
                                    field="name"
                                    :name="'moves[' . $idx . '][product_id]'"
                                    relation="many2one"
                                    :selected="old('moves.' . $idx . '.product_id', $move->product_id)"
                                    compact />
                            </td>
                            <td class="py-1.5 w-32">
                                <x-relation-dropdown
                                    table="inventory_uoms"
                                    field="name"
                                    :name="'moves[' . $idx . '][uom_id]'"
                                    relation="many2one"
                                    :selected="old('moves.' . $idx . '.uom_id', $move->uom_id)"
                                    compact />
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

                    {{-- New moves: Alpine-rendered with inline search pickers --}}
                    <tbody x-data="{
                        newMoves: [],
                        nextIdx: {{ $picking->moves->count() }},
                        productUrl: @js(route('relation-dropdown.lookup', ['table' => 'inventory_products'])),
                        uomUrl: @js(route('relation-dropdown.lookup', ['table' => 'inventory_uoms'])),
                        async fetchProducts(m) {
                            const res = await fetch(this.productUrl + '?field=name&search=' + encodeURIComponent(m.productSearch), {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            const data = await res.json();
                            m.productOptions = data.data || [];
                        },
                        async fetchUoms(m) {
                            const res = await fetch(this.uomUrl + '?field=name&search=' + encodeURIComponent(m.uomSearch), {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            const data = await res.json();
                            m.uomOptions = data.data || [];
                        },
                        selectProduct(m, opt) {
                            m.productId = opt.id;
                            m.productSearch = opt.label;
                            m.productOpen = false;
                        },
                        selectUom(m, opt) {
                            m.uomId = opt.id;
                            m.uomSearch = opt.label;
                            m.uomOpen = false;
                        },
                        addMove() {
                            this.newMoves.push({
                                idx: this.nextIdx++,
                                productId: '', productSearch: '', productOpen: false, productOptions: [],
                                uomId: '', uomSearch: '', uomOpen: false, uomOptions: [],
                                qty: 1
                            });
                        }
                    }">
                        <template x-for="(m, j) in newMoves" :key="m.idx">
                            <tr class="border-b border-gray-50">
                                <td class="py-1.5">
                                    <input type="hidden" :name="'moves['+m.idx+'][product_id]'" :value="m.productId">
                                    <div class="relative" @click.outside="m.productOpen = false">
                                        <input type="text" x-model="m.productSearch"
                                            @focus="m.productOpen = true; fetchProducts(m)"
                                            @input.debounce.250ms="fetchProducts(m)"
                                            placeholder="Search product..."
                                            class="w-full text-sm bg-transparent border-0 border-b border-dotted border-gray-300 focus:border-purple-500 focus:outline-none px-0 py-1">
                                        <div x-show="m.productOpen" style="display:none"
                                            class="absolute left-0 top-full z-40 w-full max-w-xs bg-white border border-gray-200 rounded-b-lg shadow-lg overflow-hidden">
                                            <div class="max-h-48 overflow-y-auto py-1">
                                                <template x-for="opt in m.productOptions" :key="opt.id">
                                                    <button type="button" @click="selectProduct(m, opt)"
                                                        class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                                        x-text="opt.label"></button>
                                                </template>
                                                <div x-show="m.productOptions.length === 0" class="px-4 py-2 text-sm text-gray-400">No results</div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-1.5 w-32">
                                    <input type="hidden" :name="'moves['+m.idx+'][uom_id]'" :value="m.uomId">
                                    <div class="relative" @click.outside="m.uomOpen = false">
                                        <input type="text" x-model="m.uomSearch"
                                            @focus="m.uomOpen = true; fetchUoms(m)"
                                            @input.debounce.250ms="fetchUoms(m)"
                                            placeholder="UoM..."
                                            class="w-full text-sm bg-transparent border-0 border-b border-dotted border-gray-300 focus:border-purple-500 focus:outline-none px-0 py-1">
                                        <div x-show="m.uomOpen" style="display:none"
                                            class="absolute left-0 top-full z-40 w-48 bg-white border border-gray-200 rounded-b-lg shadow-lg overflow-hidden">
                                            <div class="max-h-48 overflow-y-auto py-1">
                                                <template x-for="opt in m.uomOptions" :key="opt.id">
                                                    <button type="button" @click="selectUom(m, opt)"
                                                        class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                                        x-text="opt.label"></button>
                                                </template>
                                                <div x-show="m.uomOptions.length === 0" class="px-4 py-2 text-sm text-gray-400">No results</div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-1.5 w-24">
                                    <input type="number" :name="'moves['+m.idx+'][product_qty]'" x-model="m.qty"
                                        step="0.001" min="0.001"
                                        class="w-full text-sm bg-transparent border-0 focus:outline-none px-0 text-right">
                                </td>
                                <td class="py-1.5 text-center w-8">
                                    <button type="button" @click="newMoves.splice(j, 1)" class="text-gray-300 hover:text-red-500">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr>
                            <td colspan="4" class="pt-2">
                                <button type="button" @click="addMove()" class="text-xs font-medium text-purple-600 hover:text-purple-700">+ Add a product</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>
@endsection
