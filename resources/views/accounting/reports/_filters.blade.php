@props([
    'filters'      => [],
    'showAsOf'     => false,
    'showJournal'  => false,
    'showAccount'  => false,
    'showPartner'  => false,
    'showState'    => false,
    'showMoveType' => false,
    'showAccountType' => false,
    'showTaxUse'   => false,
    'showPartnerScope' => false,
    'reportKey'    => null,
])

@php
    $dateFrom    = $filters['date_from']    ?? null;
    $dateTo      = $filters['date_to']      ?? null;
    $asOf        = $filters['as_of']        ?? now()->toDateString();
    $journalId   = $filters['journal_id']   ?? null;
    $accountId   = $filters['account_id']   ?? null;
    $partnerId   = $filters['partner_id']   ?? null;
    $state       = $filters['state']        ?? null;
    $moveType    = $filters['move_type']    ?? null;
    $accountType = $filters['account_type'] ?? null;
    $taxUse      = $filters['tax_use']      ?? null;
    $partnerScope = $filters['partner_scope'] ?? 'ar_ap';
    $preset      = $filters['preset']       ?? null;

    $presets = [
        'today'        => 'Today',
        'this_month'   => 'This Month',
        'last_month'   => 'Last Month',
        'this_quarter' => 'This Quarter',
        'last_quarter' => 'Last Quarter',
        'this_year'    => 'This Year',
        'last_year'    => 'Last Year',
        'ytd'          => 'Year to Date',
    ];

    $canExport = auth()->user()?->hasPermission('accounting.export') ?? false;
    $exportRoute = $reportKey ? route('accounting.reports.export', $reportKey) : null;
@endphp

<form method="GET" class="mb-4 bg-white rounded-xl border border-gray-200 shadow-sm">
    {{-- Top row: date presets + export --}}
    @if(!$showAsOf)
    <div class="flex items-center gap-2 px-4 py-2.5 border-b border-gray-100 flex-wrap">
        <span class="text-xs font-semibold text-gray-500 uppercase me-1">Period</span>
        @foreach($presets as $key => $label)
        <button type="submit" name="preset" value="{{ $key }}"
                class="px-3 py-1 text-xs font-medium rounded border {{ $preset === $key ? 'bg-purple-50 border-[#71639e] text-[#71639e]' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50' }}">
            {{ $label }}
        </button>
        @endforeach
        <button type="button" onclick="this.closest('form').querySelector('[name=preset]:not(button)')?.remove(); this.closest('form').reset();"
                class="px-3 py-1 text-xs font-medium rounded border bg-white border-gray-200 text-gray-500 hover:bg-gray-50">
            Clear
        </button>

        @if($canExport && $exportRoute)
        <div class="ms-auto flex items-center gap-1" x-data="{ open: false }" @click.outside="open = false">
            <button type="button" @click="open = !open"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#71639e] text-white text-xs font-semibold rounded shadow-sm hover:bg-[#5c527f]">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                </svg>
                Export
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M5.5 7.5l4.5 4.5 4.5-4.5z"/></svg>
            </button>
            <div x-show="open" x-transition style="display:none"
                 class="absolute z-30 mt-9 me-0 right-4 bg-white border border-gray-200 rounded shadow-lg min-w-32 overflow-hidden">
                @foreach(['xlsx' => 'Excel (.xlsx)', 'csv' => 'CSV', 'pdf' => 'PDF'] as $fmt => $label)
                <button type="submit" formaction="{{ $exportRoute }}" name="format" value="{{ $fmt }}"
                        class="block w-full text-left px-4 py-2 text-xs text-gray-700 hover:bg-purple-50">
                    {{ $label }}
                </button>
                @endforeach
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- Bottom row: explicit filter controls --}}
    <div class="flex flex-wrap items-end gap-3 px-4 py-3">
        @if($showAsOf)
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('accounting.field_as_of') }}</label>
            <input type="date" name="as_of" value="{{ $asOf }}"
                   class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
        </div>
        @else
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('accounting.field_date_from') }}</label>
            <input type="date" name="date_from" value="{{ $dateFrom }}"
                   class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('accounting.field_date_to') }}</label>
            <input type="date" name="date_to" value="{{ $dateTo }}"
                   class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
        </div>
        @endif

        @if($showJournal)
        <div class="min-w-52">
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('accounting.field_journal') }}</label>
            <x-relation-dropdown table="account_journals" field="name" name="journal_id" :compact="true" :selected="$journalId" />
        </div>
        @endif

        @if($showAccount)
        <div class="min-w-52">
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('accounting.col_account') }}</label>
            <x-relation-dropdown table="accounts" field="name" name="account_id" :compact="true" :selected="$accountId" />
        </div>
        @endif

        @if($showPartner)
        <div class="min-w-52">
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('accounting.col_partner') }}</label>
            <x-relation-dropdown table="contacts" field="name" name="partner_id" :compact="true" :selected="$partnerId" />
        </div>
        @endif

        @if($showState)
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('accounting.col_state') }}</label>
            <select name="state" class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
                <option value="">All (Posted by default)</option>
                <option value="draft"     {{ $state === 'draft'     ? 'selected' : '' }}>Draft</option>
                <option value="posted"    {{ $state === 'posted'    ? 'selected' : '' }}>Posted</option>
                <option value="cancelled" {{ $state === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
        </div>
        @endif

        @if($showMoveType)
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('accounting.col_type') }}</label>
            <select name="move_type" class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
                <option value="">All</option>
                <option value="entry"       {{ $moveType === 'entry'       ? 'selected' : '' }}>Journal Entry</option>
                <option value="out_invoice" {{ $moveType === 'out_invoice' ? 'selected' : '' }}>Customer Invoice</option>
                <option value="in_invoice"  {{ $moveType === 'in_invoice'  ? 'selected' : '' }}>Vendor Bill</option>
                <option value="out_refund"  {{ $moveType === 'out_refund'  ? 'selected' : '' }}>Credit Note</option>
                <option value="in_refund"   {{ $moveType === 'in_refund'   ? 'selected' : '' }}>Vendor Refund</option>
            </select>
        </div>
        @endif

        @if($showAccountType)
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Account Type</label>
            <select name="account_type" class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
                <option value="">All Types</option>
                <optgroup label="Assets">
                    @foreach(\App\Services\Accounting\AccountingReportService::ASSET_TYPES as $t)
                    <option value="{{ $t }}" {{ $accountType === $t ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $t)) }}</option>
                    @endforeach
                </optgroup>
                <optgroup label="Liabilities">
                    @foreach(\App\Services\Accounting\AccountingReportService::LIAB_TYPES as $t)
                    <option value="{{ $t }}" {{ $accountType === $t ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $t)) }}</option>
                    @endforeach
                </optgroup>
                <optgroup label="Equity">
                    @foreach(\App\Services\Accounting\AccountingReportService::EQUITY_TYPES as $t)
                    <option value="{{ $t }}" {{ $accountType === $t ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $t)) }}</option>
                    @endforeach
                </optgroup>
                <optgroup label="Income / Expense">
                    @foreach(array_merge(\App\Services\Accounting\AccountingReportService::INCOME_TYPES, \App\Services\Accounting\AccountingReportService::EXPENSE_TYPES) as $t)
                    <option value="{{ $t }}" {{ $accountType === $t ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $t)) }}</option>
                    @endforeach
                </optgroup>
            </select>
        </div>
        @endif

        @if($showTaxUse)
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Tax Use</label>
            <select name="tax_use" class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
                <option value="">All</option>
                <option value="sale"     {{ $taxUse === 'sale'     ? 'selected' : '' }}>Sale (Output VAT)</option>
                <option value="purchase" {{ $taxUse === 'purchase' ? 'selected' : '' }}>Purchase (Input VAT)</option>
                <option value="none"     {{ $taxUse === 'none'     ? 'selected' : '' }}>None</option>
            </select>
        </div>
        @endif

        @if($showPartnerScope)
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Scope</label>
            <select name="partner_scope" class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
                <option value="ar_ap"      {{ $partnerScope === 'ar_ap'      ? 'selected' : '' }}>AR + AP</option>
                <option value="receivable" {{ $partnerScope === 'receivable' ? 'selected' : '' }}>Receivable only</option>
                <option value="payable"    {{ $partnerScope === 'payable'    ? 'selected' : '' }}>Payable only</option>
                <option value="all"        {{ $partnerScope === 'all'        ? 'selected' : '' }}>All accounts</option>
            </select>
        </div>
        @endif

        <div class="ms-auto flex items-center gap-2">
            <button type="submit" class="px-4 py-1.5 bg-[#71639e] text-white text-sm font-medium rounded hover:bg-[#5c527f]">{{ __('accounting.btn_filter') }}</button>
            <a href="{{ url()->current() }}" class="px-4 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">Reset</a>
        </div>
    </div>
</form>
