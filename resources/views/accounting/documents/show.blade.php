@extends('layouts.app')
@section('title', $document->name ?: $config['singular'])

@php
    $stateColor = match($document->state) {
        'posted'    => 'bg-green-100 text-green-700',
        'draft'     => 'bg-amber-100 text-amber-700',
        'cancelled' => 'bg-gray-200 text-gray-600',
        default     => 'bg-gray-100 text-gray-600',
    };
    $lineAmount = fn($line) => in_array($config['move_type'], ['out_invoice', 'in_refund'], true) ? (float) $line->credit : (float) $line->debit;
@endphp

@section('content')
<div class="flex flex-col h-full bg-gray-50" x-data="{ payOpen: false }">
    @can('create', \App\Models\Accounting\AccountMove::class)
        @if(!empty($config['routes']['create']))
            @php $newHref = route($config['routes']['create']); @endphp
        @endif
    @endcan
    <x-toolbar :new-href="$newHref ?? null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <a href="{{ route($config['routes']['index']) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $config['title'] }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $document->name ?: __('accounting.status_draft') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @if($document->isDraft())
                    @can('update', $document)
                    @if(!empty($config['routes']['edit']))
                    <a href="{{ route($config['routes']['edit'], $document) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('accounting.btn_edit') }}</a>
                    @endif
                    @endcan
                    @can('post', $document)
                    <form method="POST" action="{{ route($config['routes']['post'], $document) }}">
                        @csrf @method('PATCH')
                        <button class="px-3 py-1.5 text-sm font-medium text-white bg-green-700 hover:bg-green-800 rounded">{{ __('accounting.btn_confirm') }}</button>
                    </form>
                    <form method="POST" action="{{ route($config['routes']['cancel'], $document) }}">
                        @csrf @method('PATCH')
                        <button class="px-3 py-1.5 text-sm text-gray-700 border border-gray-300 rounded hover:bg-gray-50">{{ __('accounting.btn_cancel') }}</button>
                    </form>
                    @endcan
                @elseif($document->isPosted())
                    @can('post', $document)
                    @if(!$document->isPaid())
                    <button type="button" @click="payOpen = !payOpen"
                            class="px-3 py-1.5 text-sm font-medium text-white bg-[#71639e] hover:bg-[#5c527f] rounded">
                        {{ __('accounting.btn_register_payment') }}
                    </button>
                    @endif
                    <x-print-action :href="route($config['routes']['print'], $document)" />
                    <x-print-action :href="route($config['routes']['print'], $document)" label="Preview" :preview="true" />
                    @if(!empty($config['routes']['credit']))
                    <form method="POST" action="{{ route($config['routes']['credit'], $document) }}">
                        @csrf
                        <button class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 rounded">Credit Note</button>
                    </form>
                    @endif
                    <form method="POST" action="{{ route($config['routes']['reset'], $document) }}">
                        @csrf @method('PATCH')
                        <button class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 rounded">{{ __('accounting.btn_reset_draft') }}</button>
                    </form>
                    @endcan
                @elseif($document->isCancelled())
                    @can('post', $document)
                    <form method="POST" action="{{ route($config['routes']['reset'], $document) }}">
                        @csrf @method('PATCH')
                        <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">{{ __('accounting.btn_reset_draft') }}</button>
                    </form>
                    @endcan
                @endif

                @can('delete', $document)
                <form method="POST" action="{{ route($config['routes']['delete'], $document) }}" x-data="{ confirming: false }">
                    @csrf @method('DELETE')
                    <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">{{ __('accounting.btn_delete') }}</button>
                    <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                        <span class="text-xs text-red-600">Delete this {{ strtolower($config['singular']) }}?</span>
                        <button type="submit" class="px-2 py-1 text-xs font-medium text-white bg-red-600 rounded">{{ __('accounting.btn_yes') }}</button>
                        <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500">{{ __('accounting.btn_cancel') }}</button>
                    </div>
                </form>
                @endcan
            </div>
        </x-slot:actions>
    </x-toolbar>

    {{-- Register Payment panel (shown when "Register Payment" button is clicked) --}}
    @can('post', $document)
    @if($document->isPosted() && !$document->isPaid())
    <div x-show="payOpen" style="display:none"
         class="shrink-0 bg-white border-b border-gray-200 px-6 py-5 shadow-sm">
        <p class="text-sm font-semibold text-gray-800 mb-4">{{ __('accounting.btn_register_payment') }}</p>
        <form method="POST" action="{{ route($config['routes']['pay'], $document) }}">
            @csrf @method('PATCH')
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">{{ __('accounting.field_amount') }}</label>
                    <input type="number" name="amount" step="0.01" min="0.01"
                           value="{{ old('amount', number_format($residual ?? 0, 2, '.', '')) }}"
                           class="w-full text-sm border border-gray-300 rounded px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">{{ __('accounting.field_date') }}</label>
                    <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}"
                           class="w-full text-sm border border-gray-300 rounded px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">{{ __('accounting.field_journal') }}</label>
                    <x-relation-dropdown
                        table="account_journals"
                        field="name"
                        name="journal_id"
                        :compact="true"
                        :selected="null"
                    />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">{{ __('accounting.field_memo') }}</label>
                    <input type="text" name="memo" maxlength="255"
                           value="{{ old('memo', 'Payment for ' . ($document->name ?: "#{$document->id}")) }}"
                           class="w-full text-sm border border-gray-300 rounded px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-purple-500">
                </div>
            </div>
            <div class="flex items-center gap-2 mt-4">
                <button type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#71639e] hover:bg-[#5c527f] rounded">
                    {{ __('accounting.btn_register_payment') }}
                </button>
                <button type="button" @click="payOpen = false" class="px-4 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                    {{ __('accounting.btn_cancel') }}
                </button>
            </div>
        </form>
    </div>
    @endif
    @endcan

    <div class="flex-1 overflow-y-auto">
        @if(session('error'))
        <div class="mx-4 mt-4 px-3 py-2 bg-red-50 border border-red-200 text-sm text-red-700 rounded">{{ session('error') }}</div>
        @endif

        {{-- Payments widget --}}
        @if($document->isPosted() && $document->payments->isNotEmpty())
        <div class="mx-4 mt-4 rounded-xl border border-green-200 bg-green-50 px-5 py-3 shadow-sm">
            <p class="text-xs font-semibold text-green-700 uppercase mb-2">{{ __('accounting.payments') }}</p>
            <div class="flex flex-wrap gap-3">
                @foreach($document->payments as $pmt)
                @php
                    $pmtColor = $document->isPaid() ? 'bg-green-100 text-green-800 border-green-300' : 'bg-blue-50 text-blue-800 border-blue-200';
                @endphp
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full border text-sm font-medium {{ $pmtColor }}">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
                    {{ number_format((float) $pmt->amount, 2) }} {{ $pmt->currency ?: $document->currency }}
                    <span class="text-xs opacity-70">{{ $pmt->journal?->name }} · {{ optional($pmt->date)->format('Y-m-d') }}</span>
                </span>
                @endforeach
            </div>
        </div>
        @endif

        <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden relative" x-data="{ tab: 'lines' }">
            @if($document->isPaid())
            <div class="absolute -right-16 top-12 w-64 rotate-45 bg-green-600 py-2 text-center text-xl font-bold text-white shadow">PAID</div>
            @endif
            <div class="p-6">
                <div class="flex items-start justify-between gap-6 mb-1">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-500">{{ match($config['move_type']) { 'out_invoice' => 'Customer Invoice', 'in_invoice' => 'Vendor Bill', 'out_refund' => 'Customer Credit Note', 'in_refund' => 'Vendor Refund', default => $config['singular'] } }}</p>
                        <h1 class="mt-2 text-4xl font-bold {{ $document->name ? 'text-gray-900' : 'text-gray-400' }}">{{ $document->name ?: __('accounting.status_draft') }}</h1>
                    </div>
                    @php
                        $statuses = [
                            ['active' => $document->isDraft(),     'label' => __('accounting.status_draft'),     'border' => '#71639e', 'bg' => 'bg-purple-50', 'text' => 'text-gray-900',   'fill' => '#faf5ff'],
                            ['active' => $document->isPosted(),    'label' => __('accounting.status_posted'),    'border' => '#16a34a', 'bg' => 'bg-green-50',  'text' => 'text-green-800', 'fill' => '#f0fdf4'],
                            ['active' => $document->isCancelled(), 'label' => __('accounting.status_cancelled'), 'border' => '#ef4444', 'bg' => 'bg-red-50',    'text' => 'text-red-700',   'fill' => '#fef2f2'],
                        ];
                    @endphp
                    <div class="flex shrink-0 items-center text-sm font-semibold">
                        @foreach($statuses as $si => $st)
                        @php $isLast = $si === count($statuses) - 1; $bc = $st['active'] ? $st['border'] : '#e5e7eb'; $fc = $st['active'] ? $st['fill'] : '#f3f4f6'; @endphp
                        <span class="relative py-2 {{ $si === 0 ? 'ps-5 pe-8' : ($isLast ? 'ps-8 pe-5' : 'ps-8 pe-8') }} {{ $st['active'] ? $st['bg'].' '.$st['text'] : 'bg-gray-100 text-gray-400' }} border border-gray-200"
                              @if($st['active']) style="border-color:{{ $st['border'] }}" @endif>
                            {{ $st['label'] }}
                            @if(!$isLast)
                            <span class="absolute" style="right:-9px;top:50%;transform:translateY(-50%);width:0;height:0;border-top:17px solid transparent;border-bottom:17px solid transparent;border-left:9px solid {{ $bc }};z-index:10;display:block"></span>
                            <span class="absolute" style="right:-8px;top:50%;transform:translateY(-50%);width:0;height:0;border-top:16px solid transparent;border-bottom:16px solid transparent;border-left:8px solid {{ $fc }};z-index:11;display:block"></span>
                            @endif
                        </span>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 mt-6">
                    <div>
                        @foreach([
                            [$config['partner_label'], $document->partner?->name],
                            [__('accounting.col_reference'), $document->ref],
                            ['Source Document', $document->invoice_origin],
                            ['Payment Status', $document->payment_state_label],
                            ['Amount Due', number_format($residual ?? 0, 2) . ' ' . $document->currency],
                        ] as [$label, $value])
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-40 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                        </div>
                        @endforeach
                        @if($document->reversedMove)
                        @php
                            $origRoute = match($document->reversedMove->move_type) {
                                'out_invoice' => 'accounting.invoices.show',
                                'in_invoice'  => 'accounting.bills.show',
                                'out_refund'  => 'accounting.credit-notes.show',
                                'in_refund'   => 'accounting.refunds.show',
                                default       => 'accounting.moves.show',
                            };
                        @endphp
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-40 shrink-0 text-sm text-gray-500">Reversed From</span>
                            <a href="{{ route($origRoute, $document->reversedMove) }}" class="flex-1 text-sm text-purple-600 hover:underline">{{ $document->reversedMove->name ?: '(Draft)' }}</a>
                        </div>
                        @endif
                        @if($document->reversal->isNotEmpty())
                        @foreach($document->reversal as $rev)
                        @php
                            $revRoute = match($rev->move_type) {
                                'out_invoice' => 'accounting.invoices.show',
                                'in_invoice'  => 'accounting.bills.show',
                                'out_refund'  => 'accounting.credit-notes.show',
                                'in_refund'   => 'accounting.refunds.show',
                                default       => 'accounting.moves.show',
                            };
                        @endphp
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-40 shrink-0 text-sm text-gray-500">{{ $loop->first ? 'Reversal(s)' : '' }}</span>
                            <a href="{{ route($revRoute, $rev) }}" class="flex-1 text-sm text-amber-600 hover:underline">{{ $rev->name ?: '(Draft)' }}</a>
                        </div>
                        @endforeach
                        @endif
                    </div>
                    <div>
                        @foreach([
                            [$config['singular'] . ' Date', optional($document->date)->format('Y-m-d')],
                            [__('accounting.col_due_date'), optional($document->invoice_date_due)->format('Y-m-d')],
                            [__('accounting.field_payment_terms'), $document->paymentTerm?->name],
                            [__('accounting.field_journal'), $document->journal?->name],
                            [__('accounting.field_currency'), $document->currency],
                        ] as [$label, $value])
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-36 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="-mx-6 mt-8 border-t border-gray-200">
                    <div class="px-8 flex items-end gap-2">
                        <button type="button" @click="tab = 'lines'" class="px-6 py-3 text-sm font-semibold border border-t-0 rounded-b bg-white"
                                :class="tab === 'lines' ? 'text-gray-900 border-gray-300' : 'text-[#71639e] border-transparent'">
                            {{ $config['singular'] }} Lines
                        </button>
                        <button type="button" @click="tab = 'journal'" class="px-6 py-3 text-sm font-semibold border border-t-0 rounded-b bg-white"
                                :class="tab === 'journal' ? 'text-gray-900 border-gray-300' : 'text-[#71639e] border-transparent'">
                            {{ __('accounting.journal_items') }}
                        </button>
                        <button type="button" @click="tab = 'other'" class="px-6 py-3 text-sm font-semibold border border-t-0 rounded-b bg-white"
                                :class="tab === 'other' ? 'text-gray-900 border-gray-300' : 'text-[#71639e] border-transparent'">
                            Other Info
                        </button>
                    </div>
                </div>

                <div x-show="tab === 'lines'" class="-mx-6 border-t border-gray-200 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-sm font-semibold text-gray-700">
                                <th class="px-6 py-3 text-left">Description</th>
                                <th class="px-4 py-3 text-left">{{ __('accounting.col_account') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('accounting.taxes') }}</th>
                                <th class="px-6 py-3 text-right">{{ __('accounting.col_amount') }}</th>
                            </tr>
                        </thead>
                        @php
                            $productLines = $documentLines->filter(fn($l) => !$l->tax_line_id);
                            $taxLines     = $document->lines->filter(fn($l) => $l->tax_line_id);
                            $untaxed      = $productLines->sum(fn($l) => $lineAmount($l));
                            $taxTotal     = $taxLines->sum(fn($l) => $lineAmount($l));
                        @endphp
                        <tbody class="divide-y divide-gray-100">
                            @forelse($productLines as $line)
                            <tr>
                                <td class="px-6 py-2 font-semibold text-[#71639e]">{{ $line->name }}</td>
                                <td class="px-4 py-2 font-semibold text-gray-700">{{ $line->account?->display_name }}</td>
                                <td class="px-4 py-2">
                                    @foreach($line->taxes as $tax)
                                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-200 me-1">{{ $tax->display_name }}</span>
                                    @endforeach
                                </td>
                                <td class="px-6 py-2 text-right tabular-nums font-semibold">{{ number_format($lineAmount($line), 2) }} {{ $document->currency }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="px-3 py-12 text-center text-sm text-gray-400">{{ __('accounting.no_lines') }}</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-gray-100 font-semibold">
                            <tr>
                                <td colspan="3" class="px-6 py-2 text-right text-gray-700">Untaxed Amount:</td>
                                <td class="px-6 py-2 text-right tabular-nums text-gray-900">{{ number_format($untaxed, 2) }} {{ $document->currency }}</td>
                            </tr>
                            @foreach($taxLines->groupBy('tax_line_id') as $taxId => $group)
                            @php $taxLine = $group->first(); @endphp
                            <tr class="text-sm font-normal">
                                <td colspan="3" class="px-6 py-1 text-right text-gray-600">{{ $taxLine->name }}:</td>
                                <td class="px-6 py-1 text-right tabular-nums text-gray-700">{{ number_format($group->sum(fn($l) => $lineAmount($l)), 2) }} {{ $document->currency }}</td>
                            </tr>
                            @endforeach
                            <tr class="text-xl border-t border-gray-300">
                                <td colspan="3" class="px-6 py-2 text-right text-gray-700">{{ __('accounting.total') }}:</td>
                                <td class="px-6 py-2 text-right tabular-nums text-gray-900">{{ number_format((float) $document->amount_total, 2) }} {{ $document->currency }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div x-show="tab === 'journal'" style="display:none" class="-mx-6 border-t border-gray-200 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-xs font-semibold text-gray-500 uppercase">
                                <th class="px-3 py-2 text-left">{{ __('accounting.col_account') }}</th>
                                <th class="px-3 py-2 text-left">{{ __('accounting.col_label') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('accounting.col_debit') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('accounting.col_credit') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($document->lines as $line)
                            <tr>
                                <td class="px-3 py-1.5 text-gray-800">{{ $line->account?->display_name }}</td>
                                <td class="px-3 py-1.5 text-gray-700">{{ $line->name }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ (float) $line->debit ? number_format((float) $line->debit, 2) : '' }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ (float) $line->credit ? number_format((float) $line->credit, 2) : '' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold text-sm">
                            <tr>
                                <td colspan="2" class="px-3 py-2 text-right text-gray-700">Journal Totals</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format($balance['debit'], 2) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format($balance['credit'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div x-show="tab === 'other'" style="display:none" class="-mx-6 border-t border-gray-200 px-8 py-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12">
                        <div>
                            @foreach([
                                [__('accounting.field_company'), $document->company?->name],
                                [$config['control_account_label'], $controlLine?->account?->display_name],
                                [__('accounting.field_incoterm'), $document->incoterm?->name],
                            ] as [$label, $value])
                            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                                <span class="w-40 text-sm text-gray-500">{{ $label }}</span>
                                <span class="text-sm text-gray-800">{{ $value ?: '—' }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if($document->narration)
                <div class="mt-6">
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-1">{{ __('accounting.field_narration') }}</p>
                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ $document->narration }}</p>
                </div>
                @endif
            </div>
        </div>

        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Accounting\AccountMove"
                :model-id="$document->id"
                :can-comment="auth()->user()->can('comment', $document)"
            />
        </div>
    </div>
</div>
@endsection
