@php
    $dateValue = old('date') ?: ($move?->date ? $move->date->format('Y-m-d') : now()->toDateString());
    $val = fn($field, $default = '') => old($field, $move?->{$field} ?? $default);
    $state = $move?->state ?? 'draft';

    $existingLines = old('lines');
    if (!$existingLines) {
        $existingLines = $move?->lines->map(fn($l) => [
            'account_id'    => $l->account_id,
            'account_label' => $l->account ? ($l->account->code . ' ' . $l->account->name) : '',
            'partner_id'    => $l->partner_id,
            'partner_label' => $l->partner?->name ?? '',
            'name'          => $l->name,
            'debit'         => (float) $l->debit,
            'credit'        => (float) $l->credit,
        ])->all();
    }
    if (empty($existingLines)) {
        $existingLines = [
            ['account_id' => null, 'account_label' => '', 'partner_id' => null, 'partner_label' => '', 'name' => '', 'debit' => 0, 'credit' => 0],
            ['account_id' => null, 'account_label' => '', 'partner_id' => null, 'partner_label' => '', 'name' => '', 'debit' => 0, 'credit' => 0],
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

<script>window._movesFormData = { lines: @json($existingLines) };</script>
<div class="p-6"
     x-data="{
        tab: 'lines',
        lines: window._movesFormData.lines,
        validationError: null,
        validationIsHard: false,
        pendingAction: '',

        init() {
            this.lines = this.lines.map(l => Object.assign({
                account_open: false, account_results: [],
                partner_open: false, partner_results: [],
            }, l));
            const form = this.$el.closest('form');
            if (form) form.addEventListener('submit', e => this._handleSubmit(e));
        },

        addLine() {
            this.lines.push({ account_id: null, account_label: '', account_open: false, account_results: [],
                              partner_id: null, partner_label: '', partner_open: false, partner_results: [],
                              name: '', debit: 0, credit: 0 });
        },
        removeLine(i) {
            if (this.lines.length <= 2) return;
            this.lines.splice(i, 1);
        },

        async fetchDropdown(i, table, q, resultsKey) {
            const url = '/relation-dropdown/' + table + '?search=' + encodeURIComponent(q || '') + '&field=name&per_page=10';
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            this.lines[i][resultsKey] = (await res.json()).data || [];
        },
        pickAccount(i, opt) {
            this.lines[i].account_id = opt.id;
            this.lines[i].account_label = opt.label;
            this.lines[i].account_results = [];
            this.lines[i].account_open = false;
        },
        pickPartner(i, opt) {
            this.lines[i].partner_id = opt.id;
            this.lines[i].partner_label = opt.label;
            this.lines[i].partner_results = [];
            this.lines[i].partner_open = false;
        },
        clearAccount(i) {
            this.lines[i].account_id = null;
            this.lines[i].account_label = '';
        },
        clearPartner(i) {
            this.lines[i].partner_id = null;
            this.lines[i].partner_label = '';
        },

        f(n) { return (Number(n) || 0).toFixed(2); },
        get totalDebit()  { return this.lines.reduce((s, l) => s + (parseFloat(l.debit)  || 0), 0); },
        get totalCredit() { return this.lines.reduce((s, l) => s + (parseFloat(l.credit) || 0), 0); },
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

        _handleSubmit(e) {
            if (this.isBalanced) { this.validationError = null; return; }
            const action = e.submitter?.value || 'save';
            e.preventDefault();
            if (action === 'post') {
                this.validationError = 'Cannot post: the entry is not balanced. Difference is ' + this.f(Math.abs(this.difference)) + '.';
                this.validationIsHard = true;
            } else {
                this.validationError = 'Entry is not balanced (difference: ' + this.f(Math.abs(this.difference)) + '). Save as draft anyway?';
                this.validationIsHard = false;
                this.pendingAction = action;
            }
            this.$el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },
        saveAnyway() {
            const form = this.$el.closest('form');
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'action'; inp.value = 'save';
            form.appendChild(inp);
            this.validationError = null;
            form.submit();
        },
     }">

    {{-- Validation banner --}}
    <template x-if="validationError !== null">
        <div class="mb-6 px-4 py-3 rounded-lg border flex items-start gap-3"
             :class="validationIsHard ? 'bg-red-50 border-red-200' : 'bg-amber-50 border-amber-200'">
            <svg class="w-5 h-5 shrink-0 mt-0.5" :class="validationIsHard ? 'text-red-500' : 'text-amber-500'" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
            </svg>
            <div class="flex-1">
                <p class="text-sm font-medium" :class="validationIsHard ? 'text-red-700' : 'text-amber-700'" x-text="validationError"></p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <template x-if="!validationIsHard">
                    <button type="button" @click="saveAnyway()"
                            class="px-3 py-1.5 text-xs font-semibold text-white bg-amber-600 hover:bg-amber-700 rounded">
                        Save Anyway
                    </button>
                </template>
                <button type="button" @click="validationError = null" class="text-gray-400 hover:text-gray-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
    </template>

    {{-- Header: title + state stepper --}}
    <div class="mb-8 flex items-start justify-between gap-6">
        <div>
            <p class="text-lg font-semibold text-gray-800">Journal Entry</p>
            <h1 class="mt-2 text-4xl font-bold text-gray-900">{{ $move?->name ?: 'Draft' }}</h1>
        </div>
        <div class="flex shrink-0 items-center text-sm font-semibold">
            <span class="relative px-8 py-2 border {{ $state === 'draft' ? 'border-[#71639e] bg-purple-50 text-gray-900' : 'border-gray-200 bg-gray-100 text-gray-400' }}">Draft</span>
            <span class="px-8 py-2 border {{ $state === 'posted' ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-200 bg-gray-100 text-gray-400' }}">Posted</span>
            @if($state === 'cancelled')
            <span class="px-8 py-2 border border-gray-400 bg-gray-200 text-gray-600">Cancelled</span>
            @endif
        </div>
    </div>

    <input type="hidden" name="move_type" value="{{ $val('move_type', 'entry') }}">

    {{-- Fields --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-4 mb-8">
        <div class="space-y-3">
            <div class="flex items-center gap-5">
                <label class="w-28 shrink-0 text-base font-semibold text-gray-800">Reference</label>
                <input type="text" name="ref" value="{{ $val('ref') }}" maxlength="128"
                       class="flex-1 text-sm text-gray-800 bg-transparent border-0 border-b border-dotted border-gray-300 focus:border-purple-500 focus:outline-none focus:ring-0 px-0 py-1">
            </div>
        </div>
        <div class="space-y-3">
            <div class="flex items-center gap-5">
                <label class="w-40 shrink-0 text-base font-semibold text-gray-800">Accounting Date</label>
                <input type="date" name="date" value="{{ $dateValue }}" required
                       class="flex-1 text-sm text-gray-800 bg-transparent border-0 border-b border-dotted border-gray-300 focus:border-purple-500 focus:outline-none focus:ring-0 px-0 py-1">
            </div>
            <div class="flex items-center gap-5">
                <label class="w-40 shrink-0 text-base font-semibold text-gray-800">Journal</label>
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
        </div>
    </div>

    {{-- Tabs --}}
    <div class="-mx-6">
        {{-- Tab bar: underline style --}}
        <div class="border-t border-b border-gray-200 px-6 flex bg-white">
            <button type="button" @click="tab = 'lines'"
                    class="px-5 py-3 text-sm font-semibold border-b-2 -mb-px bg-white transition-colors"
                    :class="tab === 'lines' ? 'border-[#71639e] text-[#71639e]' : 'border-transparent text-gray-500 hover:text-gray-700'">
                Journal Items
            </button>
            <button type="button" @click="tab = 'other'"
                    class="px-5 py-3 text-sm font-semibold border-b-2 -mb-px bg-white transition-colors"
                    :class="tab === 'other' ? 'border-[#71639e] text-[#71639e]' : 'border-transparent text-gray-500 hover:text-gray-700'">
                Other Info
            </button>
        </div>

        {{-- Journal Items tab --}}
        <div x-show="tab === 'lines'">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 border-b border-gray-200">
                    <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 text-left w-72">Account</th>
                        <th class="px-4 py-3 text-left w-48">Partner</th>
                        <th class="px-4 py-3 text-left">Label</th>
                        <th class="px-4 py-3 text-right w-36">Debit</th>
                        <th class="px-4 py-3 text-right w-36">Credit</th>
                        <th class="px-4 py-3 w-10"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="(line, i) in lines" :key="'l'+i">
                    <tr class="bg-white hover:bg-gray-50/50">
                        {{-- Account --}}
                        <td class="px-4 py-2 relative" @click.outside="line.account_open = false">
                            <input type="hidden" :name="'lines[' + i + '][account_id]'" :value="line.account_id || ''">
                            <div class="min-h-7 border-b border-dotted focus-within:border-purple-500 flex items-center gap-1"
                                 :class="line.account_id ? 'border-transparent' : 'border-gray-300'">
                                <input type="text"
                                       x-model="line.account_label"
                                       @input.debounce.250ms="fetchDropdown(i, 'accounts', line.account_label, 'account_results')"
                                       @focus="line.account_open = true; fetchDropdown(i, 'accounts', line.account_label, 'account_results')"
                                       @blur="setTimeout(() => line.account_open = false, 150)"
                                       @keydown.escape="line.account_open = false"
                                       placeholder="Search account…"
                                       autocomplete="off"
                                       class="flex-1 min-w-0 border-0 bg-transparent px-0 py-1 text-sm text-gray-800 focus:outline-none focus:ring-0">
                                <button type="button" x-show="line.account_id" @click.prevent="clearAccount(i)"
                                        class="shrink-0 text-gray-300 hover:text-gray-500" style="display:none">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                            <div x-show="line.account_open && line.account_results.length > 0"
                                 class="absolute left-0 top-full z-40 w-80 bg-white border border-gray-200 rounded-b-lg shadow-lg max-h-56 overflow-y-auto"
                                 style="display:none">
                                <template x-for="opt in line.account_results" :key="opt.id">
                                    <button type="button" @mousedown.prevent="pickAccount(i, opt)"
                                            class="w-full flex items-center px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                                            x-text="opt.label">
                                    </button>
                                </template>
                            </div>
                            <div x-show="line.account_open && line.account_results.length === 0 && line.account_label.length > 1"
                                 class="absolute left-0 top-full z-40 w-80 bg-white border border-gray-200 rounded-b-lg shadow-lg px-4 py-2 text-sm text-gray-400"
                                 style="display:none">
                                No accounts found
                            </div>
                        </td>
                        {{-- Partner --}}
                        <td class="px-4 py-2 relative" @click.outside="line.partner_open = false">
                            <input type="hidden" :name="'lines[' + i + '][partner_id]'" :value="line.partner_id || ''">
                            <div class="min-h-7 border-b border-dotted focus-within:border-purple-500 flex items-center gap-1"
                                 :class="line.partner_id ? 'border-transparent' : 'border-gray-300'">
                                <input type="text"
                                       x-model="line.partner_label"
                                       @input.debounce.250ms="fetchDropdown(i, 'contacts', line.partner_label, 'partner_results')"
                                       @focus="line.partner_open = true; fetchDropdown(i, 'contacts', line.partner_label, 'partner_results')"
                                       @blur="setTimeout(() => line.partner_open = false, 150)"
                                       @keydown.escape="line.partner_open = false"
                                       placeholder="Partner…"
                                       autocomplete="off"
                                       class="flex-1 min-w-0 border-0 bg-transparent px-0 py-1 text-sm text-gray-800 focus:outline-none focus:ring-0">
                                <button type="button" x-show="line.partner_id" @click.prevent="clearPartner(i)"
                                        class="shrink-0 text-gray-300 hover:text-gray-500" style="display:none">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                            <div x-show="line.partner_open && line.partner_results.length > 0"
                                 class="absolute left-0 top-full z-40 w-64 bg-white border border-gray-200 rounded-b-lg shadow-lg max-h-56 overflow-y-auto"
                                 style="display:none">
                                <template x-for="opt in line.partner_results" :key="opt.id">
                                    <button type="button" @mousedown.prevent="pickPartner(i, opt)"
                                            class="w-full flex items-center px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                                            x-text="opt.label">
                                    </button>
                                </template>
                            </div>
                        </td>
                        {{-- Label --}}
                        <td class="px-4 py-2">
                            <input type="text" :name="'lines[' + i + '][name]'" x-model="line.name" maxlength="255"
                                   class="w-full border-0 border-b border-dotted border-gray-300 focus:border-purple-500 bg-transparent px-0 py-1 text-sm text-gray-700 focus:outline-none focus:ring-0">
                        </td>
                        {{-- Debit --}}
                        <td class="px-4 py-2 text-right">
                            <input type="number" step="0.0001" min="0"
                                   :name="'lines[' + i + '][debit]'"
                                   :value="line.debit || ''"
                                   @input="onDebitInput(i, $event.target.value)"
                                   class="w-full text-right border-0 border-b border-dotted border-gray-300 focus:border-purple-500 bg-transparent px-0 py-1 text-sm tabular-nums focus:outline-none focus:ring-0"
                                   placeholder="0.00">
                        </td>
                        {{-- Credit --}}
                        <td class="px-4 py-2 text-right">
                            <input type="number" step="0.0001" min="0"
                                   :name="'lines[' + i + '][credit]'"
                                   :value="line.credit || ''"
                                   @input="onCreditInput(i, $event.target.value)"
                                   class="w-full text-right border-0 border-b border-dotted border-gray-300 focus:border-purple-500 bg-transparent px-0 py-1 text-sm tabular-nums focus:outline-none focus:ring-0"
                                   placeholder="0.00">
                        </td>
                        {{-- Remove --}}
                        <td class="px-4 py-2 text-center">
                            <button type="button" @click="removeLine(i)"
                                    class="text-gray-300 hover:text-red-500 transition-colors"
                                    :disabled="lines.length <= 2"
                                    :class="lines.length <= 2 ? 'opacity-30 cursor-not-allowed' : ''">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4h6v3m-9 0h12"/></svg>
                            </button>
                        </td>
                    </tr>
                    </template>
                    <tr class="bg-gray-50 border-t border-gray-100">
                        <td colspan="6" class="px-8 py-3">
                            <button type="button" @click="addLine()"
                                    class="text-sm font-semibold text-[#71639e] hover:text-[#5c527f]">
                                Add a line
                            </button>
                        </td>
                    </tr>
                </tbody>
                <tfoot class="border-t border-gray-200">
                    <tr class="bg-gray-50 text-sm font-semibold">
                        <td colspan="3" class="px-4 py-2.5 text-right text-gray-600">Total</td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-900" x-text="f(totalDebit)"></td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-900" x-text="f(totalCredit)"></td>
                        <td></td>
                    </tr>
                    <tr class="text-sm font-medium border-t border-gray-100"
                        :class="isBalanced ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'">
                        <td colspan="3" class="px-4 py-2 text-right">Difference</td>
                        <td colspan="2" class="px-4 py-2 text-right tabular-nums" x-text="f(difference)"></td>
                        <td class="px-4 py-2 text-center">
                            <span x-show="isBalanced"
                                  class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded bg-green-100 text-green-700">
                                Balanced
                            </span>
                            <span x-show="!isBalanced"
                                  class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded bg-amber-100 text-amber-700">
                                Unbalanced
                            </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Other Info tab --}}
        <div x-show="tab === 'other'" style="display:none" class="px-8 py-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-4">
                <div class="space-y-3">
                    <div class="flex items-center gap-4">
                        <label class="w-32 shrink-0 text-sm font-semibold text-gray-700">Company</label>
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
                    <div class="flex items-center gap-4">
                        <label class="w-32 shrink-0 text-sm font-semibold text-gray-700">Currency</label>
                        <input type="text" name="currency" value="{{ $val('currency') }}" maxlength="10"
                               placeholder="IQD"
                               class="flex-1 text-sm text-gray-800 bg-transparent border-0 border-b border-dotted border-gray-300 focus:border-purple-500 focus:outline-none focus:ring-0 px-0 py-1">
                    </div>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center gap-4">
                        <label class="w-32 shrink-0 text-sm font-semibold text-gray-700">Partner</label>
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
                </div>
            </div>
            <div class="mt-6">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Notes</label>
                <textarea name="narration" rows="4"
                          class="w-full text-sm border border-gray-200 rounded px-3 py-2 focus:outline-none focus:ring-0 focus:border-purple-500 resize-none">{{ $val('narration') }}</textarea>
            </div>
        </div>
    </div>
</div>
