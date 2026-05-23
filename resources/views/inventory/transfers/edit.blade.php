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
            <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm p-6" x-data="{
                moves: {{ Js::from($picking->moves->map(fn($m) => ['id' => $m->id, 'product_name' => $m->product?->name ?? '', 'product_id' => $m->product_id, 'uom_id' => $m->uom_id, 'product_qty' => $m->product_qty, 'delete' => false])->values()) }},
                addMove() { this.moves.push({ id: null, product_name: '', product_id: '', uom_id: '', product_qty: 1, delete: false }); }
            }">
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
                    <thead><tr class="border-b border-gray-100">
                        <th class="py-1.5 text-left text-xs text-gray-500">Product</th>
                        <th class="py-1.5 text-right text-xs text-gray-500 w-24">Quantity</th>
                        <th class="w-8"></th>
                    </tr></thead>
                    <tbody>
                        <template x-for="(m, i) in moves" :key="i">
                            <tr class="border-b border-gray-50" :class="m.delete ? 'opacity-40' : ''">
                                <td class="py-1.5">
                                    <input type="hidden" :name="'moves['+i+'][id]'" :value="m.id">
                                    <input type="hidden" :name="'moves['+i+'][delete]'" :value="m.delete ? 1 : 0">
                                    <input type="hidden" :name="'moves['+i+'][product_id]'" :value="m.product_id">
                                    <input type="hidden" :name="'moves['+i+'][uom_id]'" :value="m.uom_id">
                                    <input type="text" :name="'moves['+i+'][product_name]'" x-model="m.product_name" placeholder="Product" :disabled="m.delete" class="w-full text-sm bg-transparent border-0 focus:outline-none px-0">
                                </td>
                                <td class="py-1.5">
                                    <input type="number" :name="'moves['+i+'][product_qty]'" x-model="m.product_qty" step="0.001" min="0.001" :disabled="m.delete" class="w-full text-sm bg-transparent border-0 focus:outline-none px-0 text-right">
                                </td>
                                <td class="py-1.5 text-center">
                                    <button type="button" @click="m.delete = !m.delete" :class="m.delete ? 'text-green-500' : 'text-gray-300 hover:text-red-500'">
                                        <template x-if="m.delete"><span class="text-xs">Undo</span></template>
                                        <template x-if="!m.delete"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></template>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <button type="button" @click="addMove()" class="text-xs font-medium text-purple-600 hover:text-purple-700">+ Add a product</button>
            </div>
        </div>
    </form>
</div>
@endsection
