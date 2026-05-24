<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->name ?: $config['singular'] }}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #1f2937; margin: 0; background: #f8fafc; }
        .page { width: 210mm; min-height: 297mm; margin: 0 auto; background: white; padding: 18mm; box-sizing: border-box; }
        .top { display: flex; justify-content: space-between; gap: 24px; border-bottom: 1px solid #e5e7eb; padding-bottom: 18px; }
        .title { font-size: 28px; font-weight: 750; margin: 6px 0 0; }
        .muted { color: #6b7280; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 700; background: #eef2ff; color: #5b4b8a; }
        .paid { background: #16a34a; color: white; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 22px; }
        .row { display: flex; border-bottom: 1px dotted #d1d5db; padding: 7px 0; font-size: 13px; }
        .label { width: 125px; color: #6b7280; font-weight: 700; }
        .value { flex: 1; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-top: 28px; font-size: 13px; }
        th { text-align: left; background: #f3f4f6; color: #374151; padding: 9px; border-bottom: 1px solid #d1d5db; }
        td { padding: 9px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .totals { margin-left: auto; margin-top: 24px; width: 280px; border-top: 1px solid #d1d5db; padding-top: 8px; }
        .total-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 14px; }
        .grand { font-size: 20px; font-weight: 800; }
        .terms { margin-top: 40px; color: #6b7280; white-space: pre-line; }
        @media print {
            body { background: white; }
            .page { width: auto; min-height: auto; margin: 0; padding: 0; }
            @page { margin: 16mm; }
        }
    </style>
</head>
<body>
    @php
        $lineAmount = fn($line) => in_array($config['move_type'], ['out_invoice', 'in_refund'], true) ? (float) $line->credit : (float) $line->debit;
        $taxLines   = $document->lines->filter(fn($l) => $l->tax_line_id);
        $untaxed    = $documentLines->sum(fn($l) => $lineAmount($l));
        $typeLabel  = match($config['move_type']) { 'out_invoice' => 'Customer Invoice', 'in_invoice' => 'Vendor Bill', 'out_refund' => 'Customer Credit Note', 'in_refund' => 'Vendor Refund', default => $config['singular'] };
    @endphp
    <main class="page">
        <section class="top">
            <div>
                <div class="muted">{{ $typeLabel }}</div>
                <h1 class="title">{{ $document->name ?: __('accounting.status_draft') }}</h1>
            </div>
            <div style="text-align:right">
                <span class="badge">{{ $document->state_label }}</span>
                @if($document->isPaid())
                    <span class="badge paid">PAID</span>
                @endif
            </div>
        </section>

        <section class="grid">
            <div>
                <div class="row"><span class="label">{{ $config['partner_label'] }}</span><span class="value">{{ $document->partner?->name ?: '—' }}</span></div>
                <div class="row"><span class="label">{{ __('accounting.col_reference') }}</span><span class="value">{{ $document->ref ?: '—' }}</span></div>
                <div class="row"><span class="label">{{ $config['control_account_label'] }}</span><span class="value">{{ $controlLine?->account?->display_name ?: '—' }}</span></div>
            </div>
            <div>
                <div class="row"><span class="label">{{ $config['singular'] }} Date</span><span class="value">{{ optional($document->date)->format('Y-m-d') }}</span></div>
                <div class="row"><span class="label">{{ __('accounting.field_journal') }}</span><span class="value">{{ $document->journal?->name ?: '—' }}</span></div>
                <div class="row"><span class="label">{{ __('accounting.field_currency') }}</span><span class="value">{{ $document->currency ?: '—' }}</span></div>
            </div>
        </section>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>{{ __('accounting.col_account') }}</th>
                    <th class="num">{{ __('accounting.col_amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($documentLines as $line)
                <tr>
                    <td>{{ $line->name }}</td>
                    <td>{{ $line->account?->display_name }}</td>
                    <td class="num"><x-money :amount="$lineAmount($line)" :currency="$document->currency" /></td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <section class="totals">
            <div class="total-row"><strong>Untaxed Amount:</strong><span><x-money :amount="(float) $untaxed" :currency="$document->currency" /></span></div>
            @foreach($taxLines->groupBy('tax_line_id') as $group)
            @php $tl = $group->first(); @endphp
            <div class="total-row"><span>{{ $tl->name }}:</span><span><x-money :amount="(float) $group->sum(fn($l) => $lineAmount($l))" :currency="$document->currency" /></span></div>
            @endforeach
            <div class="total-row grand"><span>{{ __('accounting.total') }}:</span><span><x-money :amount="(float) $document->amount_total" :currency="$document->currency" /></span></div>
        </section>

        @if($document->narration)
            <section class="terms">{{ $document->narration }}</section>
        @endif
    </main>
    <script>
        window.addEventListener('load', () => window.print());
    </script>
</body>
</html>
