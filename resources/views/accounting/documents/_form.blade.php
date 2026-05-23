@php
    $dateValue = old('date') ?: ($document?->date ? $document->date->format('Y-m-d') : now()->toDateString());
    $val = fn($field, $default = '') => old($field, $document?->{$field} ?? $default);

    $existingItems = old('items');
    if (!$existingItems && $document) {
        $isInvoice = $config['move_type'] === 'out_invoice';
        $controlLine = $document->lines
            ->filter(fn($line) => $isInvoice ? (float) $line->debit > 0 : (float) $line->credit > 0)
            ->sortByDesc(fn($line) => max((float) $line->debit, (float) $line->credit))
            ->first();

        $existingItems = $document->lines
            // Reject the control line and auto-generated tax lines
            ->reject(fn($line) => ($controlLine && $line->id === $controlLine->id) || $line->tax_line_id)
            ->map(function ($line) use ($isInvoice) {
                $amount = $isInvoice ? (float) $line->credit : (float) $line->debit;
                $line->loadMissing('taxes');
                return [
                    'account_id'    => $line->account_id,
                    'account_label' => $line->account ? ($line->account->code . ' ' . $line->account->name) : '',
                    'name'          => $line->name,
                    'quantity'      => 1,
                    'price_unit'    => $amount,
                    'tax_ids'       => $line->taxes->pluck('id')->all(),
                    'tax_labels'    => $line->taxes->map(fn($t) => ['id' => $t->id, 'label' => $t->display_name])->all(),
                ];
            })->values()->all();
    }

    $existingItems = $existingItems ?: [['product_id' => null, 'product_label' => '', 'uom_id' => null, 'uom_label' => '', 'account_id' => null, 'account_label' => '', 'name' => '', 'quantity' => '', 'price_unit' => '', 'tax_ids' => [], 'tax_labels' => []]];

    $lines = collect($existingItems)->map(fn($item) => [
        'product_id'    => $item['product_id'] ?? '',
        'product_label' => $item['product_label'] ?? '',
        'uom_id'        => $item['uom_id'] ?? '',
        'uom_label'     => $item['uom_label'] ?? '',
        'account_id'    => $item['account_id'] ?? '',
        'account_label' => $item['account_label'] ?? '',
        'name'          => $item['name'] ?? '',
        'quantity'      => $item['quantity'] ?? '',
        'price_unit'    => $item['price_unit'] ?? '',
        'tax_ids'       => $item['tax_ids'] ?? [],
        'tax_labels'    => $item['tax_labels'] ?? [],
    ])->values()->all();

    // Taxes available for selection (scoped to move type and company)
    $companyId = old('company_id', $document?->company_id ?? ($defaults['company_id'] ?? null));
    $taxScope  = $config['move_type'] === 'out_invoice' ? 'sale' : 'purchase';
    $availableTaxes = $companyId
        ? \App\Models\Accounting\AccountTax::where('company_id', $companyId)
            ->whereIn('type_tax_use', [$taxScope, 'none'])
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'amount_type', 'amount'])
            ->map(fn($t) => ['id' => $t->id, 'label' => $t->display_name])
            ->all()
        : [];
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
window._docFormData = {
    lines: @json($lines),
    currency: @json($val('currency', '')),
    availableTaxes: @json($availableTaxes)
};
</script>
<div class="p-6"
     x-data="{
        tab: 'lines',
        lines: window._docFormData.lines,
        currency: window._docFormData.currency,
        availableTaxes: window._docFormData.availableTaxes,
        emptyLine() {
            return { product_id: '', product_label: '', product_open: false, product_results: [], uom_id: '', uom_label: '', uom_open: false, uom_results: [], account_id: '', account_label: '', account_open: false, account_results: [], name: '', quantity: '', price_unit: '', tax_ids: [], tax_labels: [], tax_open: false };
        },
        init() {
            this.lines = this.lines.map(l => Object.assign(this.emptyLine(), l));
        },
        addLine() {
            this.lines.push(this.emptyLine());
        },
        removeLine(i) {
            if (this.lines.length <= 1) {
                this.lines = [this.emptyLine()];
                return;
            }
            this.lines.splice(i, 1);
        },
        toggleTax(i, tax) {
            const idx = this.lines[i].tax_ids.indexOf(tax.id);
            if (idx === -1) {
                this.lines[i].tax_ids.push(tax.id);
                this.lines[i].tax_labels.push(tax);
            } else {
                this.lines[i].tax_ids.splice(idx, 1);
                this.lines[i].tax_labels.splice(idx, 1);
            }
        },
        removeTax(i, taxId) {
            const idx = this.lines[i].tax_ids.indexOf(taxId);
            if (idx !== -1) {
                this.lines[i].tax_ids.splice(idx, 1);
                this.lines[i].tax_labels.splice(idx, 1);
            }
        },
        f(n) { return (Number(n) || 0).toFixed(2); },
        lineNet(i) {
            const line = this.lines[i] || {};
            return (parseFloat(line.quantity) || 0) * (parseFloat(line.price_unit) || 0);
        },
        lineTax(i) {
            const net = this.lineNet(i);
            let tax = 0;
            (this.lines[i]?.tax_ids || []).forEach(tid => {
                const t = this.availableTaxes.find(x => x.id == tid);
                if (t) { /* label format: Name (X%) — fetched from availableTaxes */ }
            });
            return tax; // detailed tax math handled server-side; UI shows zero for now
        },
        get subtotal() {
            return this.lines.reduce((sum, line) => sum + ((parseFloat(line.quantity) || 0) * (parseFloat(line.price_unit) || 0)), 0);
        },
        get total() { return this.subtotal; },
        lineTotal(i) { return this.lineNet(i); },
        accountLabel(i) {
            return this.lines[i]?.account_label || '';
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
        async productSearch(i, q) {
            if (!q || q.length < 1) { this.lines[i].product_results = []; return; }
            const res = await fetch('/relation-dropdown/inventory_products?search=' + encodeURIComponent(q) + '&field=name&per_page=10', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            this.lines[i].product_results = json.data || [];
        },
        pickProduct(i, opt) {
            this.lines[i].product_id    = opt.id;
            this.lines[i].product_label = opt.label;
            this.lines[i].product_results = [];
            this.lines[i].product_open  = false;
            // Auto-populate name from product if still blank
            if (!this.lines[i].name) this.lines[i].name = opt.label;
        },
        async uomSearch(i, q) {
            if (!q || q.length < 1) { this.lines[i].uom_results = []; return; }
            const res = await fetch('/relation-dropdown/inventory_uoms?search=' + encodeURIComponent(q) + '&field=name&per_page=10', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            this.lines[i].uom_results = json.data || [];
        },
        pickUom(i, opt) {
            this.lines[i].uom_id    = opt.id;
            this.lines[i].uom_label = opt.label;
            this.lines[i].uom_results = [];
            this.lines[i].uom_open  = false;
        },
     }">
    <div class="mb-8 flex items-start justify-between gap-6">
        <input type="hidden" name="move_type" value="{{ $config['move_type'] }}">
        <div>
            <p class="text-lg font-semibold text-gray-800">{{ $config['singular'] === 'Invoice' ? 'Customer Invoice' : 'Vendor Bill' }}</p>
            @if($document?->name)
                <h1 class="mt-2 text-4xl font-bold text-gray-900">{{ $document->name }}</h1>
            @else
                <h1 class="mt-2 text-4xl font-bold text-gray-900">Draft</h1>
            @endif
        </div>
        <div class="flex shrink-0 items-center text-sm font-semibold">
            <span class="relative px-8 py-2 border border-[#71639e] bg-purple-50 text-gray-900">Draft</span>
            <span class="px-8 py-2 border border-gray-200 bg-gray-200 text-gray-500">Posted</span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-4 mb-8">
        <div class="space-y-3">
            <div class="flex items-center gap-5">
                <label class="w-28 shrink-0 text-base font-semibold text-gray-800">{{ $config['partner_label'] }}</label>
                <x-relation-dropdown
                    :table="$config['partner_table']"
                    field="name"
                    name="partner_id"
                    relation="many2one"
                    :compact="true"
                    :selected="old('partner_id', $document?->partner_id)"
                    class="flex-1"
                />
            </div>
            <div class="flex items-center gap-5">
                <label class="w-28 shrink-0 text-sm font-semibold text-gray-700">Reference</label>
                <input type="text" name="ref" value="{{ $val('ref') }}" maxlength="128" class="flex-1 text-sm text-gray-800 bg-transparent border-0 border-b border-dotted border-gray-300 focus:border-purple-500 focus:outline-none focus:ring-0 px-0 py-1">
            </div>
        </div>
        <div class="space-y-3">
            <div class="flex items-center gap-5">
                <label class="w-32 shrink-0 text-base font-semibold text-gray-800">{{ $config['singular'] }} Date</label>
                <input type="date" name="date" value="{{ $dateValue }}" required
                       @change="const d = document.querySelector('[name=invoice_date_due]'); if (d && document.querySelector('[name=payment_term_id]')?.value) d.value = '';"
                       class="flex-1 text-sm text-gray-800 bg-transparent border-0 border-b border-dotted border-gray-300 focus:border-purple-500 focus:outline-none focus:ring-0 px-0 py-1">
            </div>
            <div class="flex items-center gap-5"
                 x-data="{ hasPaymentTerm: {{ old('payment_term_id', $document?->payment_term_id) ? 'true' : 'false' }} }"
                 @payment-term-change.window="hasPaymentTerm = !!$event.detail.value; if (hasPaymentTerm) { $nextTick(() => { const d = $el.querySelector('[name=invoice_date_due]'); if(d) d.value=''; }); }">
                <label class="w-32 shrink-0 text-base font-semibold text-gray-800">Due Date</label>
                <input type="date"
                       name="invoice_date_due"
                       value="{{ old('invoice_date_due', $document?->invoice_date_due ? $document->invoice_date_due->format('Y-m-d') : '') }}"
                       x-show="!hasPaymentTerm"
                       class="flex-1 text-sm text-gray-600 bg-transparent border-0 border-b border-dotted border-gray-300 focus:border-purple-500 focus:outline-none focus:ring-0 px-0 py-1">
                <span class="text-sm font-semibold text-gray-700" x-show="!hasPaymentTerm">or</span>
                <div class="flex-1">
                    <x-relation-dropdown
                        table="accounting_payment_terms"
                        field="name"
                        name="payment_term_id"
                        relation="many2one"
                        :compact="true"
                        :selected="old('payment_term_id', $document?->payment_term_id)"
                        event="payment-term-change"
                        class="w-full"
                    />
                </div>
            </div>
            <div class="flex items-center gap-5">
                <label class="w-32 shrink-0 text-base font-semibold text-gray-800">Journal</label>
                <x-relation-dropdown
                    table="account_journals"
                    field="name"
                    name="journal_id"
                    relation="many2one"
                    :compact="true"
                    :selected="old('journal_id', $document?->journal_id ?? ($defaults['journal_id'] ?? null))"
                    class="flex-1"
                />
                <span class="text-sm font-semibold text-gray-700">in</span>
                <input type="text" name="currency" x-model="currency" maxlength="10" class="w-28 text-sm text-gray-800 bg-transparent border-0 border-b border-dotted border-gray-300 focus:border-purple-500 focus:outline-none focus:ring-0 px-0 py-1" placeholder="IQD">
            </div>
        </div>
    </div>

    <div class="-mx-6 border-t border-gray-200">
        <div class="px-8 pt-0 flex items-end gap-2">
            <button type="button" @click="tab = 'lines'" class="px-6 py-3 text-sm font-semibold border border-t-0 rounded-b bg-white"
                    :class="tab === 'lines' ? 'text-gray-900 border-gray-300' : 'text-[#71639e] border-transparent'">
                {{ $config['singular'] }} Lines
            </button>
            <button type="button" @click="tab = 'journal'" class="px-6 py-3 text-sm font-semibold border border-t-0 rounded-b bg-white"
                    :class="tab === 'journal' ? 'text-gray-900 border-gray-300' : 'text-[#71639e] border-transparent'">
                Journal Items
            </button>
            <button type="button" @click="tab = 'other'" class="px-6 py-3 text-sm font-semibold border border-t-0 rounded-b bg-white"
                    :class="tab === 'other' ? 'text-gray-900 border-gray-300' : 'text-[#71639e] border-transparent'">
                Other Info
            </button>
        </div>

        <div x-show="tab === 'lines'" class="border-t border-gray-200">
            <table class="w-full text-sm">
                <thead class="bg-gray-200">
                    <tr class="text-sm font-semibold text-gray-700">
                        <th class="px-4 py-3 text-left w-[30%]">Product</th>
                        <th class="px-4 py-3 text-left">Account</th>
                        <th class="px-4 py-3 text-left">Analytic</th>
                        <th class="px-4 py-3 text-right w-28">Quantity</th>
                        <th class="px-4 py-3 text-left w-24">UoM</th>
                        <th class="px-4 py-3 text-right w-28">Price</th>
                        <th class="px-4 py-3 text-left w-28">Taxes</th>
                        <th class="px-4 py-3 text-right w-36">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <template x-for="(line, i) in lines" :key="'document-line-' + i">
                    <tr class="bg-white">
                        <td class="px-4 py-2 relative" @click.outside="line.product_open = false">
                            <input type="hidden" :name="'items[' + i + '][product_id]'" :value="line.product_id">
                            <input type="text"
                                   x-model="line.product_label"
                                   @input.debounce.300ms="productSearch(i, line.product_label)"
                                   @focus="line.product_open = true"
                                   @blur="setTimeout(() => line.product_open = false, 150)"
                                   placeholder="Search product…"
                                   autocomplete="off"
                                   class="w-full text-sm font-medium text-gray-700 bg-white border-0 border-b border-purple-400 focus:outline-none focus:ring-0 px-0 py-1">
                            <div x-show="line.product_open && line.product_results.length > 0"
                                 class="absolute left-0 top-full z-20 w-72 bg-white border border-gray-200 rounded shadow-lg max-h-52 overflow-y-auto"
                                 style="display:none">
                                <template x-for="opt in line.product_results" :key="opt.id">
                                    <div @mousedown.prevent="pickProduct(i, opt)"
                                         class="px-3 py-1.5 text-sm text-gray-700 hover:bg-purple-50 cursor-pointer"
                                         x-text="opt.label"></div>
                                </template>
                            </div>
                            {{-- Editable label shown below the product picker --}}
                            <input type="text" :name="'items[' + i + '][name]'" x-model="line.name" maxlength="255" class="w-full text-xs text-gray-500 border-0 border-b border-dotted border-gray-200 focus:border-purple-400 focus:outline-none focus:ring-0 px-0 py-0.5 mt-0.5" placeholder="Description (optional)">
                        </td>
                        <td class="px-4 py-2 relative">
                            <input type="hidden" :name="'items[' + i + '][account_id]'" :value="line.account_id">
                            <input type="text"
                                   x-model="line.account_label"
                                   @input.debounce.300ms="accountSearch(i, line.account_label)"
                                   @focus="line.account_open = true"
                                   @blur="setTimeout(() => line.account_open = false, 150)"
                                   placeholder="Search account…"
                                   autocomplete="off"
                                   class="w-full text-sm font-medium text-gray-700 bg-white border-0 border-b border-purple-400 focus:outline-none focus:ring-0 px-0 py-1">
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
                        <td class="px-4 py-2 text-gray-400"></td>
                        <td class="px-4 py-2 text-right">
                            <input type="number" step="0.0001" min="0" :name="'items[' + i + '][quantity]'" x-model="line.quantity" class="w-full text-right text-sm tabular-nums border-0 focus:outline-none focus:ring-0 px-0 py-1" placeholder="0.00">
                        </td>
                        <td class="px-4 py-2 relative" @click.outside="line.uom_open = false">
                            <input type="hidden" :name="'items[' + i + '][uom_id]'" :value="line.uom_id">
                            <input type="text"
                                   x-model="line.uom_label"
                                   @input.debounce.300ms="uomSearch(i, line.uom_label)"
                                   @focus="line.uom_open = true"
                                   @blur="setTimeout(() => line.uom_open = false, 150)"
                                   placeholder="UoM"
                                   autocomplete="off"
                                   class="w-full text-sm text-gray-700 bg-white border-0 border-b border-dotted border-gray-300 focus:border-purple-500 focus:outline-none focus:ring-0 px-0 py-1">
                            <div x-show="line.uom_open && line.uom_results.length > 0"
                                 class="absolute left-0 top-full z-20 w-40 bg-white border border-gray-200 rounded shadow-lg max-h-48 overflow-y-auto"
                                 style="display:none">
                                <template x-for="opt in line.uom_results" :key="opt.id">
                                    <div @mousedown.prevent="pickUom(i, opt)"
                                         class="px-3 py-1.5 text-sm text-gray-700 hover:bg-purple-50 cursor-pointer"
                                         x-text="opt.label"></div>
                                </template>
                            </div>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <input type="number" step="0.0001" min="0" :name="'items[' + i + '][price_unit]'" x-model="line.price_unit" class="w-full text-right text-sm tabular-nums border-0 border-b border-dotted border-gray-300 focus:border-purple-500 focus:outline-none focus:ring-0 px-0 py-1" placeholder="0.00">
                        </td>
                        {{-- Taxes column --}}
                        <td class="px-4 py-2 relative" @click.outside="line.tax_open = false">
                            <template x-for="(tl, ti) in (line.tax_labels || [])" :key="'tl-' + ti">
                                <span class="inline-flex items-center gap-0.5 mr-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                    <span x-text="tl.label"></span>
                                    <button type="button" @click.stop="removeTax(i, tl.id)" class="text-blue-400 hover:text-blue-700 ms-0.5">×</button>
                                </span>
                            </template>
                            <template x-for="(tid, tii) in (line.tax_ids || [])" :key="'tid-' + tii">
                                <input type="hidden" :name="'items[' + i + '][tax_ids][]'" :value="tid">
                            </template>
                            @if(count($availableTaxes) > 0)
                            <button type="button" @click.stop="line.tax_open = !line.tax_open"
                                    class="text-xs text-[#71639e] hover:text-[#5c527f] underline underline-offset-2">
                                + Tax
                            </button>
                            <div x-show="line.tax_open" style="display:none"
                                 class="absolute left-0 top-full z-20 w-56 bg-white border border-gray-200 rounded shadow-lg max-h-48 overflow-y-auto">
                                @foreach($availableTaxes as $availTax)
                                <div @mousedown.prevent="toggleTax(i, availableTaxes.find(t => t.id === {{ $availTax['id'] }}))"
                                     class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-purple-50 cursor-pointer">
                                    <span :class="line.tax_ids.includes({{ $availTax['id'] }}) ? 'text-purple-700 font-semibold' : ''">
                                        {{ $availTax['label'] }}
                                    </span>
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right font-semibold tabular-nums text-gray-700">
                            <div class="flex items-center justify-end gap-3">
                                <span><span x-text="f(lineNet(i))"></span> <span x-text="currency || ''"></span></span>
                                <button type="button" @click="removeLine(i)" class="text-gray-400 hover:text-red-500" title="Remove line">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4h6v3m-9 0h12"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    </template>
                    <tr class="bg-gray-50">
                        <td colspan="8" class="px-8 py-3">
                            <div class="flex items-center gap-5 text-sm font-semibold text-[#71639e]">
                                <button type="button" @click="addLine()" class="hover:text-[#5c3d55]">Add a line</button>
                                <button type="button" @click="addLine()" class="hover:text-[#5c3d55]">Add a section</button>
                                <button type="button" @click="addLine()" class="hover:text-[#5c3d55]">Add a note</button>
                                <button type="button" class="hover:text-[#5c3d55]">Catalog</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="min-h-64 border-t border-gray-200 px-8 py-8">
                <div class="ms-auto w-full max-w-sm border-t border-gray-200 pt-2 text-sm">
                    <div class="flex items-center justify-between py-1">
                        <span class="font-semibold text-gray-600">Untaxed Amount:</span>
                        <span class="tabular-nums text-gray-700"><span x-text="f(subtotal)"></span> <span x-text="currency || ''"></span></span>
                    </div>
                    <div class="flex items-center justify-between py-1 text-xs text-gray-400">
                        <span>Taxes:</span>
                        <span class="tabular-nums">Computed on save</span>
                    </div>
                    <div class="flex items-center justify-between py-1 text-lg font-bold border-t border-gray-200 mt-1">
                        <span class="text-gray-700">Total:</span>
                        <span class="tabular-nums text-gray-900"><span x-text="f(subtotal)"></span> <span x-text="currency || ''"></span></span>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="tab === 'journal'" style="display:none" class="border-t border-gray-200">
            <table class="w-full text-sm">
                <thead class="bg-gray-200">
                    <tr class="text-sm font-semibold text-gray-700">
                        <th class="px-4 py-3 text-left">Account</th>
                        <th class="px-4 py-3 text-left">Label</th>
                        <th class="px-4 py-3 text-right">Debit</th>
                        <th class="px-4 py-3 text-right">Credit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <template x-for="(line, i) in lines" :key="'journal-line-' + i">
                    <tr>
                        <td class="px-4 py-2 text-gray-700" x-text="accountLabel(i)"></td>
                        <td class="px-4 py-2 text-gray-700" x-text="line.name"></td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $config['move_type'] === 'in_invoice' ? '' : '0.00' }}<span x-show="@js($config['move_type'] === 'in_invoice')" x-text="f(lineTotal(i))"></span></td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $config['move_type'] === 'out_invoice' ? '' : '0.00' }}<span x-show="@js($config['move_type'] === 'out_invoice')" x-text="f(lineTotal(i))"></span></td>
                    </tr>
                    </template>
                    <tr class="bg-gray-50 font-semibold">
                        <td class="px-4 py-2 text-gray-700">{{ $config['control_account_label'] }}</td>
                        <td class="px-4 py-2 text-gray-700">{{ $config['move_type'] === 'out_invoice' ? 'Customer balance' : 'Vendor balance' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $config['move_type'] === 'out_invoice' ? '' : '0.00' }}<span x-show="@js($config['move_type'] === 'out_invoice')" x-text="f(total)"></span></td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $config['move_type'] === 'in_invoice' ? '' : '0.00' }}<span x-show="@js($config['move_type'] === 'in_invoice')" x-text="f(total)"></span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-show="tab === 'other'" style="display:none" class="border-t border-gray-200 px-8 py-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-4">
                <div class="space-y-3">
                    <div class="flex items-center gap-4">
                        <label class="w-40 shrink-0 text-sm font-semibold text-gray-700">Company</label>
                        <x-relation-dropdown
                            table="companies"
                            field="name"
                            name="company_id"
                            relation="many2one"
                            :compact="true"
                            :selected="old('company_id', $document?->company_id ?? ($defaultCompanyId ?? null))"
                            class="flex-1"
                        />
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="w-40 shrink-0 text-sm font-semibold text-gray-700">{{ $config['control_account_label'] }}</label>
                        <x-relation-dropdown
                            table="accounts"
                            field="name"
                            name="control_account_id"
                            relation="many2one"
                            :compact="true"
                            :selected="old('control_account_id', $defaults['control_account_id'] ?? null)"
                            class="flex-1"
                        />
                    </div>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center gap-4">
                        <label class="w-40 shrink-0 text-sm font-semibold text-gray-700">Incoterm</label>
                        <x-relation-dropdown
                            table="accounting_incoterms"
                            field="name"
                            name="incoterm_id"
                            relation="many2one"
                            :compact="true"
                            :selected="old('incoterm_id', $document?->incoterm_id)"
                            class="flex-1"
                        />
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="w-40 shrink-0 text-sm font-semibold text-gray-700">Source Document</label>
                        <input type="text" name="invoice_origin" value="{{ $val('invoice_origin') }}" maxlength="128"
                               class="flex-1 text-sm text-gray-800 bg-transparent border-0 border-b border-dotted border-gray-300 focus:border-purple-500 focus:outline-none focus:ring-0 px-0 py-1"
                               placeholder="e.g. PO/2026/00001">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Terms and Conditions</label>
                        <textarea name="narration" rows="5" class="w-full text-sm border border-gray-200 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-purple-500" placeholder="Terms and Conditions">{{ $val('narration') }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
