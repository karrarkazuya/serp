@php $isEdit = isset($paymentTerm); @endphp
<div
    x-data="{
        lines: {{ $isEdit ? $paymentTerm->lines->map(fn($l) => ['value_type'=>$l->value_type,'value'=>$l->value,'days'=>$l->days])->toJson() : '[]' }},
        addLine() { this.lines.push({ value_type: 'balance', value: 0, days: 0 }); },
        removeLine(i) { this.lines.splice(i, 1); }
    }"
    class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 max-w-2xl">

    @if($isEdit)
    <input type="hidden" name="_method" value="PUT">
    @endif

    {{-- Company --}}
    @if(!$isEdit)
    <div class="flex items-start gap-4 py-3 border-b border-gray-100">
        <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">Company <span class="text-red-500">*</span></label>
        <div class="flex-1">
            <x-relation-dropdown
                name="company_id"
                table="companies"
                :value="old('company_id', $defaultCompanyId ?? '')"
                placeholder="Select company…"
                required />
            @error('company_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>
    @endif

    {{-- Name --}}
    <div class="flex items-start gap-4 py-3 border-b border-gray-100">
        <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">Name <span class="text-red-500">*</span></label>
        <div class="flex-1">
            <input type="text" name="name" value="{{ old('name', $paymentTerm->name ?? '') }}"
                   class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400"
                   placeholder="e.g. 30 days" required>
            @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    {{-- Note --}}
    <div class="flex items-start gap-4 py-3 border-b border-gray-100">
        <label class="w-40 shrink-0 text-sm font-medium text-gray-600 pt-1.5">Note</label>
        <div class="flex-1">
            <textarea name="note" rows="2"
                      class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400"
                      placeholder="Optional description…">{{ old('note', $paymentTerm->note ?? '') }}</textarea>
        </div>
    </div>

    {{-- Active --}}
    <div class="flex items-center gap-4 py-3 border-b border-gray-100">
        <label class="w-40 shrink-0 text-sm font-medium text-gray-600">Active</label>
        <input type="hidden" name="active" value="0">
        <input type="checkbox" name="active" value="1"
               {{ old('active', $paymentTerm->active ?? true) ? 'checked' : '' }}
               class="w-4 h-4 text-purple-600 rounded">
    </div>

    {{-- Lines --}}
    <div class="mt-5">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-semibold text-gray-700">Payment Lines</h3>
            <button type="button" @click="addLine()" class="text-xs text-purple-600 hover:text-purple-800 font-medium">+ Add Line</button>
        </div>
        <table class="w-full text-sm border border-gray-200 rounded">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase w-32">Type</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase w-28">Value</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase w-24">Days</th>
                    <th class="px-3 py-2 w-8"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(line, i) in lines" :key="i">
                    <tr class="border-t border-gray-100">
                        <td class="px-3 py-1.5">
                            <select :name="`lines[${i}][value_type]`" x-model="line.value_type"
                                    class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
                                <option value="percent">Percent</option>
                                <option value="fixed">Fixed</option>
                                <option value="balance">Balance</option>
                            </select>
                        </td>
                        <td class="px-3 py-1.5">
                            <input type="number" :name="`lines[${i}][value]`" x-model="line.value" step="0.01" min="0"
                                   class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
                        </td>
                        <td class="px-3 py-1.5">
                            <input type="number" :name="`lines[${i}][days]`" x-model="line.days" step="1" min="0"
                                   class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
                        </td>
                        <td class="px-3 py-1.5 text-center">
                            <button type="button" @click="removeLine(i)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                        </td>
                    </tr>
                </template>
                <tr x-show="lines.length === 0" class="border-t border-gray-100">
                    <td colspan="4" class="px-3 py-3 text-xs text-gray-400 text-center">No lines. Click "+ Add Line" to add a payment schedule.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
