{{-- M3 / Odoo parity: currency-aware money rendering. See app/View/Components/Money.php. --}}
<span {{ $attributes->merge(['class' => 'tabular-nums']) }}>{{ $formatted }}</span>
