<?php

namespace App\View\Components;

use App\Models\Accounting\Currency;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * MC1 (Odoo parity): currency-aware amount rendering. Replaces bare
 * `number_format($amount, 2)` calls so that:
 *   - JPY / IQD render with 0 decimals (1,235 not 1,234.57)
 *   - BHD / KWD render with 3 decimals (1,234.568 not 1,234.57)
 *   - The currency symbol is positioned correctly (before for $/€, after for د.ع)
 *
 * Usage:
 *   <x-money :amount="$inv->amount_total" :currency="$inv->currency" />
 *   <x-money :amount="$line->debit" />            {{-- defaults to company base currency --}}
 *   <x-money :amount="0" currency="USD" />        {{-- explicit, falls through gracefully --}}
 *
 * When the currency code isn't in the lookup, falls back to a safe
 * `number_format($amount, 2) . ' ' . $currency` so legacy data still renders.
 */
class Money extends Component
{
    public string $formatted;

    public function __construct(
        public float $amount = 0.0,
        public ?string $currency = null,
        public bool $blank = false,
    ) {
        if ($blank && abs($amount) < 1e-9) {
            $this->formatted = '';
            return;
        }

        $code = $currency
            ?? auth()->user()?->defaultCompany?->currency
            ?? 'USD';

        $resolved = Currency::byCode($code);

        $this->formatted = $resolved
            ? $resolved->format($amount)
            : number_format($amount, 2) . ' ' . $code;
    }

    public function render(): View|Closure|string
    {
        return view('components.money');
    }
}
