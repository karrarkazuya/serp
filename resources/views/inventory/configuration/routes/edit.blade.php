@extends('layouts.app')
@section('title', 'Edit: ' . $route->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <form method="POST" action="{{ route('inventory.config.routes.update', $route) }}" class="flex flex-col h-full">
        @csrf @method('PUT')
        <x-toolbar>
            <x-slot:breadcrumb>
                <a href="{{ route('inventory.config.routes.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Routes</a>
                <a href="{{ route('inventory.config.routes.show', $route) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $route->name }}</a>
                <span class="text-sm font-semibold text-gray-800">Edit</span>
            </x-slot:breadcrumb>
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.config.routes.show', $route) }}" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">Discard</a>
                    <button type="submit" class="px-3 py-1.5 text-sm font-semibold text-white bg-[#714B67] hover:bg-[#5c3d55] rounded">Save</button>
                </div>
            </x-slot:actions>
        </x-toolbar>
        <div class="flex-1 overflow-y-auto">
            <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm p-6">
                @if($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                    <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
                @endif

                <div class="mb-5">
                    <input type="text" name="name" value="{{ old('name', $route->name) }}" required
                           class="w-full text-2xl font-bold text-gray-900 border-0 border-b-2 focus:outline-none focus:border-purple-500 pb-1 bg-transparent border-gray-200">
                </div>

                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Rules</h3>
                    <table class="w-full text-sm mb-3">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="py-1.5 text-left text-xs text-gray-500">Name</th>
                                <th class="py-1.5 text-left text-xs text-gray-500 w-48">Operation Type</th>
                                <th class="py-1.5 text-left text-xs text-gray-500 w-48">Source Location</th>
                                <th class="py-1.5 text-left text-xs text-gray-500 w-48">Destination Location</th>
                                <th class="py-1.5 text-left text-xs text-gray-500 w-28">Action</th>
                                <th class="py-1.5 text-right text-xs text-gray-500 w-16">Seq.</th>
                                <th class="w-8"></th>
                            </tr>
                        </thead>

                        {{-- Existing rules --}}
                        <tbody>
                            @foreach($route->rules as $idx => $rule)
                            <tr x-data="{ deleted: false }" :class="deleted ? 'opacity-40' : ''" class="border-b border-gray-50">
                                <td class="py-1.5">
                                    <input type="hidden" name="rules[{{ $idx }}][id]" value="{{ $rule->id }}">
                                    <input type="hidden" name="rules[{{ $idx }}][delete]" :value="deleted ? 1 : 0">
                                    <input type="text" name="rules[{{ $idx }}][name]" value="{{ old('rules.' . $idx . '.name', $rule->name) }}"
                                        placeholder="Rule name" :disabled="deleted"
                                        class="w-full text-sm bg-transparent border-0 focus:outline-none px-0">
                                </td>
                                <td class="py-1.5 w-48">
                                    <x-relation-dropdown
                                        table="inventory_operation_types"
                                        field="name"
                                        :name="'rules[' . $idx . '][operation_type_id]'"
                                        relation="many2one"
                                        :selected="old('rules.' . $idx . '.operation_type_id', $rule->operation_type_id)"
                                        compact />
                                </td>
                                <td class="py-1.5 w-48">
                                    <x-relation-dropdown
                                        table="inventory_locations"
                                        field="complete_name"
                                        :name="'rules[' . $idx . '][source_location_id]'"
                                        relation="many2one"
                                        :selected="old('rules.' . $idx . '.source_location_id', $rule->source_location_id)"
                                        compact />
                                </td>
                                <td class="py-1.5 w-48">
                                    <x-relation-dropdown
                                        table="inventory_locations"
                                        field="complete_name"
                                        :name="'rules[' . $idx . '][destination_location_id]'"
                                        relation="many2one"
                                        :selected="old('rules.' . $idx . '.destination_location_id', $rule->destination_location_id)"
                                        compact />
                                </td>
                                <td class="py-1.5 w-28">
                                    <select name="rules[{{ $idx }}][action]" :disabled="deleted" class="text-sm bg-transparent border-0 focus:outline-none px-0">
                                        <option value="pull" @selected(old('rules.' . $idx . '.action', $rule->action) === 'pull')>Pull</option>
                                        <option value="push" @selected(old('rules.' . $idx . '.action', $rule->action) === 'push')>Push</option>
                                        <option value="pull_push" @selected(old('rules.' . $idx . '.action', $rule->action) === 'pull_push')>Pull & Push</option>
                                    </select>
                                </td>
                                <td class="py-1.5 w-16">
                                    <input type="number" name="rules[{{ $idx }}][sequence]"
                                        value="{{ old('rules.' . $idx . '.sequence', $rule->sequence) }}"
                                        min="0" :disabled="deleted"
                                        class="w-16 text-sm bg-transparent border-0 focus:outline-none px-0 text-right">
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

                        {{-- New rules added client-side via server-rendered partial --}}
                        <tbody x-data="{ nextIdx: {{ $route->rules->count() }}, newRuleRowUrl: @js(route('inventory.config.routes.new-rule-row')) }">
                            <tr>
                                <td colspan="7" class="pt-2">
                                    <button type="button" class="text-xs font-medium text-purple-600 hover:text-purple-700"
                                        @click="fetch(newRuleRowUrl + '?idx=' + nextIdx, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                                            .then(r => r.text())
                                            .then(html => {
                                                const addRow = $el.closest('tr');
                                                addRow.insertAdjacentHTML('beforebegin', html);
                                                Alpine.initTree(addRow.previousElementSibling);
                                                nextIdx++;
                                            })">+ Add a rule</button>
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
