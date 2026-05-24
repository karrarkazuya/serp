@extends('layouts.app')
@section('title', __('accounting.nav_settings_lock'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('accounting.nav_settings_lock') }}</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        @if(session('success'))
        <div class="px-3 py-2 bg-green-50 border border-green-200 text-sm text-green-700 rounded">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="px-3 py-2 bg-red-50 border border-red-200 text-sm text-red-700 rounded">{{ session('error') }}</div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-1">{{ __('accounting.section_lock_dates') }}</h2>
            <p class="text-sm text-gray-500 mb-6">
                {!! __('accounting.lock_dates_desc') !!}
            </p>

            @foreach($companies as $company)
            <div class="border border-gray-200 rounded-lg p-5 mb-4 last:mb-0">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-800">{{ $company->name }}</h3>
                        <p class="text-xs text-gray-400">{{ __('accounting.base_currency', ['currency' => $company->currency ?: 'IQD']) }}</p>
                    </div>
                </div>

                @if(!auth()->user()->hasPermission('accounting.lock'))
                <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2 mb-4">
                    {!! __('accounting.lock_perm_required') !!}
                </div>
                @endif

                <form method="POST" action="{{ route('accounting.settings.update', $company) }}">
                    @csrf @method('PUT')

                    {{-- Lock dates --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                {{ __('accounting.field_period_lock') }}
                                <span class="font-normal text-gray-400 ms-1">{{ __('accounting.field_period_lock_soft') }}</span>
                            </label>
                            <input type="date"
                                   name="accounting_period_lock_date"
                                   value="{{ $company->accounting_period_lock_date?->format('Y-m-d') }}"
                                   @disabled(!auth()->user()->hasPermission('accounting.lock'))
                                   class="w-full text-sm border border-gray-300 rounded px-3 py-2 focus:border-purple-500 focus:outline-none focus:ring-0 disabled:bg-gray-50 disabled:text-gray-400">
                            <p class="mt-1 text-xs text-gray-400">{{ __('accounting.period_lock_hint') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                {{ __('accounting.field_fiscal_lock') }}
                                <span class="font-normal text-red-400 ms-1">{{ __('accounting.field_fiscal_lock_hard') }}</span>
                            </label>
                            <input type="date"
                                   name="accounting_fiscal_year_lock_date"
                                   value="{{ $company->accounting_fiscal_year_lock_date?->format('Y-m-d') }}"
                                   @disabled(!auth()->user()->hasPermission('accounting.lock'))
                                   class="w-full text-sm border border-gray-300 rounded px-3 py-2 focus:border-purple-500 focus:outline-none focus:ring-0 disabled:bg-gray-50 disabled:text-gray-400">
                            <p class="mt-1 text-xs text-gray-400">{{ __('accounting.fiscal_lock_hint') }}</p>
                        </div>
                    </div>

                    {{-- MC3 (Odoo parity): Multi-Currency setup
                         The base currency lives on the company record itself
                         and is not editable here (it's set at company create).
                         Allowed currencies + FX gain/loss accounts are the
                         operational knobs that let cross-currency invoices,
                         payments, and reconciliation function.  --}}
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wide mb-1">
                            Multi-Currency
                        </h4>
                        <p class="text-xs text-gray-500 mb-4">
                            Currencies this company can invoice / bill / pay in, and the GL accounts that record FX gain or loss when cross-currency reconciliation leaves a residual.
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">
                                    Allowed Currencies
                                </label>
                                <p class="text-xs text-gray-400 mb-2">
                                    The base currency
                                    <code class="px-1 py-0.5 bg-gray-100 rounded">{{ $company->currency ?: 'IQD' }}</code>
                                    is always allowed. Selecting an empty list means "any active currency".
                                </p>
                                @php $selectedIds = $company->allowedCurrencies->pluck('id')->all(); @endphp
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 max-h-48 overflow-y-auto border border-gray-200 rounded p-3">
                                    @foreach($currencies as $currency)
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox"
                                               name="allowed_currency_ids[]"
                                               value="{{ $currency->id }}"
                                               @checked(in_array($currency->id, $selectedIds, true))
                                               @disabled(!auth()->user()->hasPermission('accounting.lock'))
                                               class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                        <span class="font-mono text-xs text-gray-500">{{ $currency->code }}</span>
                                        <span class="text-gray-700 truncate">{{ $currency->name }}</span>
                                    </label>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">
                                    FX Gain Account
                                </label>
                                <select name="income_currency_exchange_account_id"
                                        @disabled(!auth()->user()->hasPermission('accounting.lock'))
                                        class="w-full text-sm border border-gray-300 rounded px-3 py-2 focus:border-purple-500 focus:outline-none focus:ring-0 disabled:bg-gray-50">
                                    <option value="">— Not configured —</option>
                                    @foreach(\App\Models\Accounting\Account::where('company_id', $company->id)->where('active', true)->where('account_type', 'like', 'income%')->orderBy('code')->get() as $acc)
                                    <option value="{{ $acc->id }}" @selected($company->income_currency_exchange_account_id == $acc->id)>{{ $acc->display_name }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-400">Posts when a foreign-currency payment closes a residual at a more favorable rate than the invoice.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">
                                    FX Loss Account
                                </label>
                                <select name="expense_currency_exchange_account_id"
                                        @disabled(!auth()->user()->hasPermission('accounting.lock'))
                                        class="w-full text-sm border border-gray-300 rounded px-3 py-2 focus:border-purple-500 focus:outline-none focus:ring-0 disabled:bg-gray-50">
                                    <option value="">— Not configured —</option>
                                    @foreach(\App\Models\Accounting\Account::where('company_id', $company->id)->where('active', true)->where('account_type', 'like', 'expense%')->orderBy('code')->get() as $acc)
                                    <option value="{{ $acc->id }}" @selected($company->expense_currency_exchange_account_id == $acc->id)>{{ $acc->display_name }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-400">Posts when a foreign-currency payment closes a residual at a worse rate than the invoice.</p>
                            </div>
                        </div>
                    </div>

                    @if(auth()->user()->hasPermission('accounting.lock'))
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-[#71639e] hover:bg-[#5c527f] rounded">
                            Save settings for {{ $company->name }}
                        </button>
                    </div>
                    @endif
                </form>
            </div>
            @endforeach

            @if($companies->isEmpty())
            <p class="text-sm text-gray-400">{{ __('accounting.no_companies') }}</p>
            @endif
        </div>
    </div>
</div>
@endsection
