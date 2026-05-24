@extends('layouts.app')
@section('title', __('accounting.payments'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <a href="{{ route('accounting.payments.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.payments') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $payment->memo ?: __('accounting.payments') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @if($payment->isDraft())
                    <form method="POST" action="{{ route('accounting.payments.confirm', $payment) }}">
                        @csrf @method('PATCH')
                        <button class="px-3 py-1.5 text-sm font-medium text-white bg-[#71639e] hover:bg-[#5c527f] rounded">{{ __('accounting.btn_confirm') }}</button>
                    </form>
                    <form method="POST" action="{{ route('accounting.payments.cancel', $payment) }}">
                        @csrf @method('PATCH')
                        <button class="px-3 py-1.5 text-sm text-gray-700 border border-gray-300 rounded hover:bg-gray-50">{{ __('accounting.btn_cancel') }}</button>
                    </form>
                    <form method="POST" action="{{ route('accounting.payments.delete', $payment) }}"
                          x-data="{ confirming: false }">
                        @csrf @method('DELETE')
                        <button type="button" x-show="!confirming" @click="confirming = true"
                                class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">{{ __('accounting.btn_delete') }}</button>
                        <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                            <span class="text-xs text-red-600">{{ __('accounting.confirm_delete_payment') }}</span>
                            <button type="submit" class="px-2 py-1 text-xs font-medium text-white bg-red-600 rounded">{{ __('accounting.btn_yes') }}</button>
                            <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500">{{ __('accounting.btn_cancel') }}</button>
                        </div>
                    </form>
                @elseif($payment->isPosted())
                    <form method="POST" action="{{ route('accounting.payments.reset-draft', $payment) }}">
                        @csrf @method('PATCH')
                        <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">{{ __('accounting.btn_reset_draft') }}</button>
                    </form>
                    <form method="POST" action="{{ route('accounting.payments.cancel', $payment) }}">
                        @csrf @method('PATCH')
                        <button class="px-3 py-1.5 text-sm text-gray-700 border border-gray-300 rounded hover:bg-gray-50">{{ __('accounting.btn_cancel') }}</button>
                    </form>
                @elseif($payment->state === 'cancelled')
                    <form method="POST" action="{{ route('accounting.payments.reset-draft', $payment) }}">
                        @csrf @method('PATCH')
                        <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">{{ __('accounting.btn_reset_draft') }}</button>
                    </form>
                @endif
                <a href="{{ route('accounting.moves.show', $payment->move) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('accounting.btn_journal_entry') }}</a>
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm">
            <div class="p-6">
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-600">{{ ucfirst($payment->payment_type) }} {{ __('accounting.payments') }}</span>
                    @if($payment->isDraft())
                        <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium bg-purple-100 text-purple-700">{{ __('accounting.status_draft') }}</span>
                    @elseif($payment->isPosted())
                        <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium bg-green-100 text-green-700">{{ __('accounting.status_in_process') }}</span>
                    @else
                        <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium bg-gray-200 text-gray-600">{{ __('accounting.status_cancelled') }}</span>
                    @endif
                </div>
                <h1 class="mt-2 text-4xl font-bold text-gray-900"><x-money :amount="(float) $payment->amount" :currency="$payment->currency" /></h1>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 mt-6">
                    <div>
                        @foreach([
                            [__('accounting.field_partner'), $payment->partner?->name],
                            [__('accounting.field_document'), $payment->pairedDocument?->display_name],
                            [__('accounting.field_memo'), $payment->memo],
                        ] as [$label, $value])
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-32 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                        </div>
                        @endforeach
                    </div>
                    <div>
                        @foreach([
                            [__('accounting.field_date'), optional($payment->date)->format('Y-m-d')],
                            [__('accounting.field_journal'), $payment->journal?->name],
                            [__('accounting.field_company'), $payment->company?->name],
                        ] as [$label, $value])
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-32 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-8 border border-gray-200 rounded-lg overflow-hidden">
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
                            @foreach($payment->move->lines as $line)
                            <tr>
                                <td class="px-3 py-1.5 text-gray-800">{{ $line->account?->display_name }}</td>
                                <td class="px-3 py-1.5 text-gray-700">{{ $line->name }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums"><x-money :amount="(float) $line->debit" :currency="$payment->currency" :blank="true" /></td>
                                <td class="px-3 py-1.5 text-right tabular-nums"><x-money :amount="(float) $line->credit" :currency="$payment->currency" :blank="true" /></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <x-chatter
            model-type="App\Models\Accounting\AccountPayment"
            :model-id="$payment->id"
            :can-comment="auth()->user()->can('comment', $payment)"
        />
    </div>
</div>
@endsection
