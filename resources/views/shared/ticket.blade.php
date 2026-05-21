<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $ticket->name }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 55%, #0f3460 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 20px;
        }

        .wrapper { width: 100%; max-width: 580px; }

        .brand {
            text-align: center;
            margin-bottom: 24px;
            color: rgba(255,255,255,0.4);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .card {
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 32px 80px rgba(0,0,0,0.5);
        }

        .banner {
            padding: 32px 28px 28px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }
        .banner-pending   { background: linear-gradient(135deg, #c0550a, #e8750f); }
        .banner-completed { background: linear-gradient(135deg, #0a8f52, #12b863); }
        .banner-closed    { background: linear-gradient(135deg, #3a3f47, #545b64); }

        .banner h1 { font-size: 20px; font-weight: 700; color: #fff; line-height: 1.35; }

        .pill {
            flex-shrink: 0;
            background: rgba(255,255,255,0.2);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.35);
            border-radius: 999px;
            padding: 3px 13px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .meta {
            display: flex;
            gap: 20px;
            padding: 12px 28px;
            background: #f7f8f9;
            border-bottom: 1px solid #eceef1;
            font-size: 12px;
            color: #888;
            flex-wrap: wrap;
        }
        .meta-item { display: flex; align-items: center; gap: 5px; }

        .fields { padding: 24px 28px; border-bottom: 1px solid #eceef1; }
        .field-row {
            display: flex;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 13px;
        }
        .field-row:last-child { border-bottom: none; }
        .field-label { width: 130px; flex-shrink: 0; color: #9ca3af; font-size: 12px; }
        .field-value { color: #1f2937; }

        .message-section { padding: 28px 28px 32px; }
        .message-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #bbb;
            margin-bottom: 14px;
        }
        .message-body { font-size: 15px; line-height: 1.75; color: #2d3035; white-space: pre-wrap; }
        .no-message { font-size: 14px; color: #bbb; font-style: italic; }

        .card-foot {
            padding: 12px 28px;
            background: #f7f8f9;
            border-top: 1px solid #eceef1;
            font-size: 11px;
            color: #bbb;
            display: flex;
            justify-content: space-between;
        }

        @media (max-width: 540px) {
            body { padding: 24px 12px; }
            .banner { padding: 22px 18px 18px; }
            .banner h1 { font-size: 17px; }
            .fields, .message-section { padding: 18px 18px; }
            .meta, .card-foot { padding: 10px 18px; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="brand">{{ $company?->name ? $company->name . ' · ' . __('workflow.support_brand') : __('workflow.support_brand') }}</div>

        <div class="card">
            <div class="banner banner-{{ $ticket->state }}">
                <h1>{{ $ticket->name }}</h1>
                <span class="pill">{{ $ticket->stateLabel() }}</span>
            </div>

            <div class="meta">
                <span class="meta-item">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    {{ $ticket->created_at->format('M d, Y') }}
                </span>
                <span class="meta-item">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    {{ __('workflow.ticket_number') }}{{ $ticket->id }}
                </span>
                @if($ticket->assignedDepartment)
                <span class="meta-item">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    {{ $ticket->assignedDepartment->name }}
                </span>
                @endif
            </div>

            @if($ticket->resolve_deadline || $ticket->priority)
            <div class="fields">
                @if($ticket->priority)
                <div class="field-row">
                    <span class="field-label">{{ __('workflow.priority_field') }}</span>
                    <span class="field-value">{{ $ticket->priorityLabel() }}</span>
                </div>
                @endif
                @if($ticket->resolve_deadline)
                <div class="field-row">
                    <span class="field-label">{{ __('workflow.deadline_field') }}</span>
                    <span class="field-value">{{ $ticket->resolve_deadline->format('M j, Y H:i') }}</span>
                </div>
                @endif
                @if($ticket->resolve_duration)
                <div class="field-row">
                    <span class="field-label">{{ __('workflow.duration_field') }}</span>
                    <span class="field-value">{{ $ticket->resolve_duration }}h</span>
                </div>
                @endif
            </div>
            @endif

            <div class="message-section">
                <div class="message-label">{{ __('workflow.message_heading') }}</div>
                @if($link->message)
                    <div class="message-body">{{ $link->message }}</div>
                @else
                    <div class="no-message">{{ __('workflow.no_message_ticket') }}</div>
                @endif
            </div>

            <div class="card-foot">
                <span>{{ $company?->name }}</span>
                <span>{{ __('workflow.confidential_footer') }}</span>
            </div>
        </div>
    </div>
</body>
</html>
