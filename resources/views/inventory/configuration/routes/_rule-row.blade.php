<tr x-data="{ deleted: false }" :class="deleted ? 'opacity-40' : ''" class="border-b border-gray-50">
    <td class="py-1.5">
        <input type="hidden" name="rules[{{ $idx }}][id]" value="">
        <input type="hidden" name="rules[{{ $idx }}][delete]" :value="deleted ? 1 : 0">
        <input type="text" name="rules[{{ $idx }}][name]" value="" placeholder="{{ __('inventory.rule_name') }}"
            :disabled="deleted"
            class="w-full text-sm bg-transparent border-0 focus:outline-none px-0">
    </td>
    <td class="py-1.5 w-48">
        <x-relation-dropdown
            table="inventory_operation_types"
            field="name"
            :name="'rules[' . $idx . '][operation_type_id]'"
            relation="many2one"
            :selected="null"
            compact />
    </td>
    <td class="py-1.5 w-48">
        <x-relation-dropdown
            table="inventory_locations"
            field="complete_name"
            :name="'rules[' . $idx . '][location_src_id]'"
            relation="many2one"
            :selected="null"
            compact />
    </td>
    <td class="py-1.5 w-48">
        <x-relation-dropdown
            table="inventory_locations"
            field="complete_name"
            :name="'rules[' . $idx . '][location_dest_id]'"
            relation="many2one"
            :selected="null"
            compact />
    </td>
    <td class="py-1.5 w-28">
        <select name="rules[{{ $idx }}][action]" :disabled="deleted" class="text-sm bg-transparent border-0 focus:outline-none px-0">
            <option value="pull">{{ __('inventory.action_pull') }}</option>
            <option value="push">{{ __('inventory.action_push') }}</option>
            <option value="pull_push">{{ __('inventory.action_pull_push') }}</option>
        </select>
    </td>
    <td class="py-1.5 w-16">
        <input type="number" name="rules[{{ $idx }}][sequence]" value="20" min="0"
            :disabled="deleted"
            class="w-16 text-sm bg-transparent border-0 focus:outline-none px-0 text-end">
    </td>
    <td class="py-1.5 text-center w-8">
        <button type="button" @click="deleted = !deleted"
            :class="deleted ? 'text-green-500' : 'text-gray-300 hover:text-red-500'">
            <template x-if="deleted"><span class="text-xs">{{ __('inventory.undo') }}</span></template>
            <template x-if="!deleted">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </template>
        </button>
    </td>
</tr>
