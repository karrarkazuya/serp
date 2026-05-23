@php
    $dateValue = old('date') ?: ($move?->date ? $move->date->format('Y-m-d') : now()->toDateString());

    $val = fn($field, $default = '') => old($field, $move?->{$field} ?? $default);

    $existingLines = old('lines');
    if (!$existingLines) {
        $existingLines = $move?->lines->map(fn($l) => [
            'account_id'    => $l->account_id,
            'account_label' => $l->account ? ($l->account->code . ' ' . $l->account->name) : '',
            'partner_id'    => $l->partner_id,
            'name'          => $l->name,
            'debit'         => (float) $l->debit,
            'credit'        => (float) $l->credit,
            'currency'      => $l->currency,
            'amount_currency' => (float) $l->amount_currency,
        ])->all();
    }
    if (!$existingLines) {
        $existingLines = old('lines', []);
    }
    if (empty($existingLines)) {
        $existingLines = [
            ['account_id' => null, 'account_label' => '', 'partner_id' => null, 'name' => '', 'debit' => 0, 'credit' => 0, 'currency' => null, 'amount_currency' => 0],
            ['account_id' => null, 'account_label' => '', 'partner_id' => null, 'name' => '', 'debit' => 0, 'credit' => 0, 'currency' => null, 'amount_currency' => 0],
        ];
    }
@endphp

@if($errors->any())
<div class="px-6 pt-4 pb-0">
    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
        <p class="text-sm font-medium text-red-700 mb-1">Please fix the errors below.</p>
        <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
</div>
@endif

@if(session('error'))
<div class="px-6 pt-4 pb-0">
    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
</div>
@endif

<script>
window._movesFormData = { lines: @json($existingLines) };
</script>
<div class="p-6"
     x-data="{
        lines: window._movesFormData.lines,
        addLine() {
            this.lines.push({ account_id: null, account_label: '', account_open: false, account_results: [], partner_id: null, name: '', debit: 0, credit: 0, currency: null, amount_currency: 0 });
        },
        async accountSearch(i, q) {
            if (!q || q.length < 1) { this.lines[i].account_results = []; return; }
            const res = await fetch('/relation-dropdown/accounts?search=' + encodeURIComponent(q) + '&field=name&per_page=10', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            this.lines[i].account_results = json.data || [];
        },
        pickAccount(i, opt) {
            this.lines[i].account_id    = opt.id;
            this.lines[i].account_label = opt.label;
            this.lines[i].account_results = [];
            this.lines[i].account_open  = false;
        },
        init() {
            this.lines = this.lines.map(l => Object.assign({ account_open: false, account_results: [] }, l));
        },
        removeLine(i) {
            if (this.lines.length <= 2) return;
            this.lines.splice(i, 1);
        },
        f(n) { return (Number(n) || 0).toFixed(2); },
        get totalDebit()  { return this.lines.reduce((s,l) => s + (parseFloat(l.debit)  || 0), 0); },
        get totalCredit() { return this.lines.reduce((s,l) => s + (parseFloat(l.credit) || 0), 0); },
        get difference()  { return Math.round((this.totalDebit - this.totalCredit) * 100) / 100; },
        get isBalanced()  { return Math.abs(this.difference) < 0.005 && this.totalDebit > 0; },
        onDebitInput(i, v) {
            this.lines[i].debit = v;
            if (parseFloat(v) > 0) this.lines[i].credit = 0;
        },
        onCreditInput(i, v) {
            this.lines[i].credit = v;
            if (parseFloat(v) > 0) this.lines[i].debit = 0;
        },
     }">

    <div class="mb-6 flex items-center gap-3">
        <span class="text-sm text-gray-500">{{ \App\Models\Accounting\AccountMove::MOVE_TYPES[$val('move_type', 'entry')] ?? 'Journal Entry' }}</span>
        <input type="hidden" name="move_type" value="{{ $val('move_type', 'entry') }}">
        @if($move?->name)
            <h1 class="text-3xl font-bold text-gray-900">{{ $move->name }}</h1>
        @else
            <h1 class="text-3xl font-bold text-gray-400">Draft</h1>
        @endif
        @if($move)
            @php
                $color = match($move->state) {
                    'posted'    => 'bg-green-100 text-green-700',
                    'draft'     => 'bg-amber-100 text-amber-700',
                    'cancelled' => 'bg-gray-200 text-gray-600',
                    default     => 'bg-gray-100 text-gray-600',
                };
            @endphp
            <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $color }}">{{ $move->state_label }}</span>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 mb-6">
        <div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-32 shrink-0 text-sm text-gray-500">Journal</label>
                <x-relation-dropdown
                    table="account_journals"
                    field="name"
                    name="journal_id"
                    relation="many2one"
                    :compact="true"
                    :selected="old('journal_id', $move?->journal_id ?? ($preselectedJournalId ?? null))"
                    class="flex-1"
                />
            </div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-32 shrink-0 text-sm text-gray-500">Date</label>
                <input type="date" name="date" value="{{ $dateValue }}" required class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
            </div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-32 shrink-0 text-sm text-gray-500">Reference</label>
                <input type="text" name="ref" value="{{ $val('ref') }}" maxlength="128" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
            </div>
        </div>
        <div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-32 shrink-0 text-sm text-gray-500">Company</label>
                <x-relation-dropdown
                    table="companies"
                    field="name"
                    name="company_id"
                    relation="many2one"
                    :compact="true"
                    :selected="old('company_id', $move?->company_id ?? ($defaultCompanyId ?? null))"
                    class="flex-1"
                />
            </div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-32 shrink-0 text-sm text-gray-500">Partner</label>
                <x-relation-dropdown
                    table="contacts"
                    field="name"
                    name="partner_id"
                    relation="many2one"
                    :compact="true"
                    :selected="old('partner_id', $move?->partner_id)"
                    class="flex-1"
                />
            </div>
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-32 shrink-0 text-sm text-gray-500">Currency</label>
                <input type="text" name="currency" value="{{ $val('currency') }}" maxlength="10" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5" placeholder="USD">
            </div>
        </div>
    </div>

    <div class="border border-gray-200 rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr class="text-xs font-semibold text-gray-500 uppercase">
                    <th class="px-3 py-2 text-left w-1/3">Account</th>
                    <th class="px-3 py-2 text-left">Label</th>
                    <th class="px-3 py-2 text-right w-32">Debit</th>
                    <th class="px-3 py-2 text-right w-32">Credit</th>
                    <th class="px-3 py-2 w-10"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <template x-for="(line, i) in lines" :key="'l'+i">
                    <tr class="bg-white">
                        <td class="px-3 py-1.5 relative">
                            <input type="hidden" :name="'lines[' + i + '][account_id]'" :value="line.account_id">
                            <input type="text"
                                   x-model="line.account_label"
                                   @input.debounce.300ms="accountSearch(i, line.account_label)"
                                   @focus="line.account_open = true"
                                   @blur="setTimeout(() => line.account_open = false, 150)"
                                   placeholder="Search account…"
                                   autocomplete="off"
                                   class="w-full text-sm bg-white border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-purple-500">
                            <div x-show="line.account_open && line.account_results.length > 0"
                                 class="absolute left-0 top-full z-20 w-72 bg-white border border-gray-200 rounded shadow-lg max-h-52 overflow-y-auto"
                                 style="display:none">
                                <template x-for="opt in line.account_results" :key="opt.id">
                                    <div @mousedown.prevent="pickAccount(i, opt)"
                                         class="px-3 py-1.5 text-sm text-gray-700 hover:bg-purple-50 cursor-pointer"
                                         x-text="opt.label"></div>
                                </template>
                            </div>
                        </td>
                        <td class="px-3 py-1.5">
                            <input type="text" :name="'lines[' + i + '][name]'" x-model="line.name" required maxlength="255" class="w-full text-sm border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
                        </td>
                        <td class="px-3 py-1.5 text-right">
                            <input type="number" step="0.0001" min="0" :name="'lines[' + i + '][debit]'" :value="line.debit" @input="onDebitInput(i, $event.target.value)" class="w-full text-right text-sm tabular-nums border-0 focus:outline-none focus:ring-0 px-0 py-0.5" placeholder="0.00">
                        </td>
                        <td class="px-3 py-1.5 text-right">
                            <input type="number" step="0.0001" min="0" :name="'lines[' + i + '][credit]'" :value="line.credit" @input="onCreditInput(i, $event.target.value)" class="w-full text-right text-sm tabular-nums border-0 focus:outline-none focus:ring-0 px-0 py-0.5" placeholder="0.00">
                        </td>
                        <td class="px-3 py-1.5 text-center">
                            <button type="button" @click="removeLine(i)" class="text-gray-300 hover:text-red-500" :disabled="lines.length <= 2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </td>
                    </tr>
                </template>
                <tr class="bg-gray-50">
                    <td colspan="5" class="px-3 py-2">
                        <button type="button" @click="addLine()" class="text-xs font-medium text-purple-600 hover:text-purple-700">+ Add line</button>
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="bg-gray-100 font-semibold text-sm">
                    <td colspan="2" class="px-3 py-2 text-right text-gray-700">Totals</td>
                    <td class="px-3 py-2 text-right tabular-nums" x-text="f(totalDebit)"></td>
                    <td class="px-3 py-2 text-right tabular-nums" x-text="f(totalCredit)"></td>
                    <td></td>
                </tr>
                <tr :class="isBalanced ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'" class="text-sm font-medium">
                    <td colspan="2" class="px-3 py-2 text-right">Difference</td>
                    <td colspan="2" class="px-3 py-2 text-right tabular-nums" x-text="f(difference)"></td>
                    <td class="px-3 py-2 text-center">
                        <span x-show="isBalanced" class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded bg-green-100 text-green-700">Balanced</span>
                        <span x-show="!isBalanced" class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded bg-amber-100 text-amber-700">Unbalanced</span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="mt-6">
        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Narration</label>
        <textarea name="narration" rows="3" class="w-full text-sm border border-gray-200 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-purple-500">{{ $val('narration') }}</textarea>
    </div>
</div>
