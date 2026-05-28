<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>{{ $title }}</title>
<style>
    @page { margin: 1.5cm 1cm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; margin: 0; }
    .header { border-bottom: 2px solid #71639e; padding-bottom: 10px; margin-bottom: 14px; }
    .header h1 { margin: 0; font-size: 16px; color: #71639e; font-weight: bold; }
    .header .sub { font-size: 9px; color: #6b7280; margin-top: 2px; }
    .meta { margin-bottom: 12px; }
    .meta table { border-collapse: collapse; }
    .meta td { padding: 2px 8px 2px 0; font-size: 9px; vertical-align: top; }
    .meta td.k { color: #6b7280; }
    .meta td.v { color: #111827; font-weight: 600; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th { background: #f3f4f6; color: #374151; font-size: 9px; font-weight: bold; text-align: left; padding: 5px 6px; border-bottom: 1px solid #d1d5db; }
    table.data th.num { text-align: right; }
    table.data td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; font-size: 9px; }
    table.data td.num { text-align: right; font-variant-numeric: tabular-nums; }
    table.data tr.totals { background: #f9fafb; font-weight: bold; }
    table.data tr.totals td { border-top: 2px solid #9ca3af; border-bottom: 0; }
    .footer { position: fixed; bottom: -0.8cm; left: 0; right: 0; text-align: center; font-size: 8px; color: #9ca3af; }
</style>
</head>
<body>
<div class="header">
    <h1>{{ $title }}</h1>
    @if($company)<div class="sub">{{ $company }}</div>@endif
</div>

@if(!empty($meta))
<div class="meta">
    <table>
        @foreach($meta as $label => $value)
        <tr><td class="k">{{ $label }}:</td><td class="v">{{ $value }}</td></tr>
        @endforeach
    </table>
</div>
@endif

<table class="data">
    <thead>
        <tr>
            @foreach($columns as $col)
            <th class="{{ ($col['align'] ?? '') === 'right' ? 'num' : '' }}">{{ $col['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($records as $record)
        <tr>
            @foreach($columns as $col)
            <td class="{{ ($col['align'] ?? '') === 'right' ? 'num' : '' }}">
                @php
                    $val = is_array($record) ? ($record[$col['key']] ?? '') : ($record->{$col['key']} ?? '');
                    if ($val instanceof \DateTimeInterface) $val = $val->format('Y-m-d');
                @endphp
                {{ $val }}
            </td>
            @endforeach
        </tr>
        @empty
        <tr><td colspan="{{ count($columns) }}" style="text-align:center; color:#9ca3af; padding:18px;">{{ __('accounting.no_data') }}</td></tr>
        @endforelse

        @if(!empty($totals))
        <tr class="totals">
            @foreach($columns as $i => $col)
            @php
                $found = collect($totals)->firstWhere('key', $col['key']);
            @endphp
            <td class="{{ ($col['align'] ?? '') === 'right' ? 'num' : '' }}">
                {{ $found['value'] ?? ($i === 0 ? 'Total' : '') }}
            </td>
            @endforeach
        </tr>
        @endif
    </tbody>
</table>

<div class="footer">{{ __('accounting.printed_at', ['date' => $printed_at, 'title' => $title]) }}</div>
</body>
</html>
