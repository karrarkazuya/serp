<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $procedure->name }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 55%, #0f3460 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 48px 20px;
        }

        .wrapper { width: 100%; max-width: 640px; }

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
        .banner-pending   { background: linear-gradient(135deg, #5b21b6, #7c3aed); }
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

        .message-section { padding: 24px 28px; border-bottom: 1px solid #eceef1; }
        .message-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #bbb;
            margin-bottom: 12px;
        }
        .message-body { font-size: 14px; line-height: 1.75; color: #2d3035; white-space: pre-wrap; }
        .no-message { font-size: 13px; color: #bbb; font-style: italic; }

        .tasks-section { padding: 24px 28px 28px; }
        .tasks-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #bbb;
            margin-bottom: 14px;
        }

        .task-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .task-item:last-child { border-bottom: none; }

        .task-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .state-pending   { background: #dbeafe; color: #1d4ed8; }
        .state-completed { background: #dcfce7; color: #15803d; }
        .state-rejected  { background: #fee2e2; color: #b91c1c; }
        .state-skipped   { background: #f1f5f9; color: #64748b; }
        .state-draft     { background: #f1f5f9; color: #9ca3af; }

        .task-name { font-size: 14px; font-weight: 600; color: #1f2937; margin-bottom: 2px; }
        .task-desc { font-size: 12px; color: #6b7280; margin-top: 2px; }
        .task-seq  { font-size: 11px; color: #d1d5db; font-weight: 600; flex-shrink: 0; width: 22px; text-align: right; padding-top: 4px; }

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
            .tasks-section, .message-section { padding: 18px; }
            .meta, .card-foot { padding: 10px 18px; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="brand">{{ $company?->name ? $company->name . ' · Workflow' : 'Workflow' }}</div>

        <div class="card">
            <div class="banner banner-{{ $procedure->state }}">
                <h1>{{ $procedure->name }}</h1>
                <span class="pill">{{ $procedure->stateLabel() }}</span>
            </div>

            <div class="meta">
                <span class="meta-item">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    {{ $procedure->created_at->format('M d, Y') }}
                </span>
                <span class="meta-item">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Procedure #{{ $procedure->id }}
                </span>
                @if($procedure->procedureTemplate)
                <span class="meta-item">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg>
                    {{ $procedure->procedureTemplate->name }}
                </span>
                @endif
            </div>

            <div class="message-section">
                <div class="message-label">Message</div>
                @if($link->message)
                    <div class="message-body">{{ $link->message }}</div>
                @else
                    <div class="no-message">No message has been provided for this procedure.</div>
                @endif
            </div>

            @if($procedure->tickets->isNotEmpty())
            <div class="tasks-section">
                <div class="tasks-title">Tickets</div>
                @foreach($procedure->tickets as $ticket)
                <div class="task-item">
                    <span class="task-seq">{{ $ticket->task_sequence }}.</span>
                    <div class="flex-1">
                        <div class="task-name">{{ $ticket->name }}</div>
                        @if($ticket->description)
                        <div class="task-desc">{{ $ticket->description }}</div>
                        @endif
                    </div>
                    <span class="task-badge state-{{ $ticket->state }}">{{ $ticket->stateLabel() }}</span>
                </div>
                @endforeach
            </div>
            @endif

            <div class="card-foot">
                <span>{{ $company?->name }}</span>
                <span>Confidential — do not forward</span>
            </div>
        </div>
    </div>
</body>
</html>
