@extends('layouts.app')
@section('title', __('settings.system'))
@include('settings._sidebar')

@section('content')
@php
    $fmt = function (?int $bytes): string {
        if ($bytes === null) return 'N/A';
        if ($bytes < 1024)         return $bytes . ' B';
        if ($bytes < 1_048_576)    return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1_073_741_824) return round($bytes / 1_048_576, 1) . ' MB';
        return round($bytes / 1_073_741_824, 2) . ' GB';
    };
    $pct = function (?int $used, ?int $total): int {
        if (!$used || !$total) return 0;
        return (int) min(100, round($used / $total * 100));
    };
    $diskUsed = ($diskTotal && $diskFree) ? ($diskTotal - $diskFree) : null;
    $envColors = [
        'production' => 'bg-green-100 text-green-800',
        'local'      => 'bg-amber-100 text-amber-800',
        'staging'    => 'bg-blue-100 text-blue-800',
    ];
    $envColor = $envColors[$appEnv] ?? 'bg-gray-100 text-gray-700';
@endphp

<div class="flex flex-col h-full bg-gray-50 overflow-y-auto">

    {{-- Top bar --}}
    <div class="bg-white border-b border-gray-200 px-6 py-3 shrink-0 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-sm font-semibold text-gray-900">{{ __('settings.system') }}</h1>
            <p class="text-xs text-gray-400 mt-0.5">{{ __('settings.system_desc') }}</p>
        </div>
        <span class="text-xs text-gray-400">{{ now()->format('M d, Y · H:i') }}</span>
    </div>

    <div class="p-6 space-y-6 max-w-5xl w-full mx-auto">

        {{-- ── Stat Cards ────────────────────────────────────────────────────── --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">{{ __('settings.total_users') }}</p>
                <p class="text-3xl font-bold text-gray-900">{{ number_format($totalUsers) }}</p>
                <p class="text-xs text-gray-500 mt-1">
                    <span class="text-green-600 font-medium">{{ number_format($activeUsers) }}</span> {{ __('common.active') }}
                </p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">{{ __('settings.online_now') }}</p>
                <p class="text-3xl font-bold text-gray-900">{{ number_format($onlineUsers) }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ __('settings.last_5_min') }}</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">{{ __('settings.total_files') }}</p>
                <p class="text-3xl font-bold text-gray-900">{{ number_format($storageFiles) }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ __('settings.across_all_disks') }}</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">{{ __('settings.storage_used') }}</p>
                <p class="text-3xl font-bold text-gray-900">{{ $fmt($storageTotal) }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ __('settings.in_files_table') }}</p>
            </div>
        </div>

        {{-- ── Two-column grid for main sections ────────────────────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- ── Application ─────────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/60 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                    <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider">{{ __('settings.application') }}</h2>
                </div>
                <dl class="divide-y divide-gray-50">
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-gray-500">{{ __('settings.environment') }}</dt>
                        <dd><span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $envColor }}">{{ $appEnv }}</span></dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-gray-500">{{ __('settings.debug_mode') }}</dt>
                        <dd>
                            @if($appDebug)
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-red-100 text-red-700">ON</span>
                            @else
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-700">OFF</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-gray-500">{{ __('settings.php_version') }}</dt>
                        <dd class="text-sm font-medium text-gray-800 font-mono">{{ $phpVersion }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-gray-500">Laravel</dt>
                        <dd class="text-sm font-medium text-gray-800 font-mono">{{ $laravelVersion }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-gray-500">{{ __('settings.timezone') }}</dt>
                        <dd class="text-sm font-medium text-gray-800">{{ $timezone }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-gray-500">{{ __('settings.session_driver') }}</dt>
                        <dd class="text-sm font-medium text-gray-800 capitalize">{{ $sessionDriver }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-gray-500">{{ __('settings.cache_driver') }}</dt>
                        <dd class="text-sm font-medium text-gray-800 capitalize">{{ $cacheDriver }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-gray-500">{{ __('settings.db_driver') }}</dt>
                        <dd class="text-sm font-medium text-gray-800 capitalize">{{ $dbDriver }}</dd>
                    </div>
                </dl>
            </div>

            {{-- ── Infrastructure ───────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/60 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                    </svg>
                    <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider">{{ __('settings.infrastructure') }}</h2>
                </div>
                <dl class="divide-y divide-gray-50">
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-gray-500">{{ __('settings.operating_system') }}</dt>
                        <dd class="text-sm font-medium text-gray-800 text-end max-w-[55%] truncate" title="{{ $phpOs }}">{{ $phpOs }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-gray-500">{{ __('settings.cpu_cores') }}</dt>
                        <dd class="text-sm font-medium text-gray-800">{{ $cpuCores }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-gray-500">{{ __('settings.total_ram') }}</dt>
                        <dd class="text-sm font-medium text-gray-800">{{ $fmt($ramBytes) }}</dd>
                    </div>
                    @if($dbSize !== null)
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-gray-500">{{ __('settings.db_size') }}</dt>
                        <dd class="text-sm font-medium text-gray-800">{{ $fmt($dbSize) }}</dd>
                    </div>
                    @endif
                    @if($diskTotal)
                    <div class="px-5 py-3">
                        <div class="flex items-center justify-between mb-2">
                            <dt class="text-sm text-gray-500">{{ __('settings.disk_usage') }}</dt>
                            <dd class="text-sm font-medium text-gray-800">
                                {{ $fmt($diskUsed) }} / {{ $fmt($diskTotal) }}
                            </dd>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                            @php $dp = $pct($diskUsed, $diskTotal); @endphp
                            <div class="h-1.5 rounded-full transition-all
                                        {{ $dp > 90 ? 'bg-red-500' : ($dp > 70 ? 'bg-amber-400' : 'bg-[#714B67]') }}"
                                 style="width: {{ $dp }}%"></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">{{ $fmt($diskFree) }} {{ __('settings.free') }}</p>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- ── Data / Counts ─────────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/60 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                    </svg>
                    <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider">{{ __('settings.data') }}</h2>
                </div>
                <dl class="divide-y divide-gray-50">
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-gray-500">{{ __('settings.companies') }}</dt>
                        <dd class="text-sm font-semibold text-gray-800">{{ number_format($totalCompanies) }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-gray-500">{{ __('settings.roles') }}</dt>
                        <dd class="text-sm font-semibold text-gray-800">{{ number_format($totalRoles) }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-gray-500">{{ __('settings.permissions') }}</dt>
                        <dd class="text-sm font-semibold text-gray-800">{{ number_format($totalPermissions) }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-gray-500">{{ __('settings.users') }}</dt>
                        <dd class="text-sm font-semibold text-gray-800">
                            {{ number_format($totalUsers) }}
                            <span class="text-xs text-gray-400 font-normal">({{ number_format($activeUsers) }} {{ __('common.active') }})</span>
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- ── Storage Breakdown ────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/60 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                    </svg>
                    <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider">{{ __('settings.storage_breakdown') }}</h2>
                </div>

                @if($storageFiles === 0)
                    <p class="px-5 py-8 text-sm text-center text-gray-400">{{ __('settings.no_files_stored') }}</p>
                @else
                    {{-- By disk --}}
                    <div class="px-5 pt-4 pb-2">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">{{ __('settings.by_disk') }}</p>
                        <div class="space-y-2">
                            @foreach($storageByDisk as $disk)
                            @php $dp2 = $pct((int)$disk->total, $storageTotal); @endphp
                            <div>
                                <div class="flex items-center justify-between mb-0.5">
                                    <span class="text-xs font-medium text-gray-700 capitalize">{{ $disk->disk }}</span>
                                    <span class="text-xs text-gray-500">{{ $fmt((int)$disk->total) }} &middot; {{ number_format((int)$disk->cnt) }} {{ __('settings.files') }}</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                    <div class="h-1.5 bg-[#714B67] rounded-full" style="width: {{ $dp2 }}%"></div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- By type --}}
                    <div class="px-5 pt-3 pb-4">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">{{ __('settings.by_type') }}</p>
                        <div class="space-y-2">
                            @foreach($storageByCategory as $cat)
                            @php $cp = $pct((int)$cat->total, $storageTotal); @endphp
                            <div>
                                <div class="flex items-center justify-between mb-0.5">
                                    <span class="text-xs font-medium text-gray-700">{{ $cat->label }}</span>
                                    <span class="text-xs text-gray-500">{{ $fmt((int)$cat->total) }} &middot; {{ number_format((int)$cat->cnt) }} {{ __('settings.files') }}</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                    <div class="h-1.5 bg-[#714B67]/60 rounded-full" style="width: {{ $cp }}%"></div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- ── Queue / Jobs ─────────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden lg:col-span-2">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/60 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                    <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider">{{ __('settings.queue_jobs') }}</h2>
                </div>

                {{-- Summary row --}}
                <div class="grid grid-cols-3 divide-x divide-gray-100 border-b border-gray-100">
                    <div class="px-5 py-4 text-center">
                        <p class="text-2xl font-bold text-gray-900">{{ number_format($queuePending) }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ __('settings.queue_pending') }}</p>
                    </div>
                    <div class="px-5 py-4 text-center">
                        <p class="text-2xl font-bold {{ $queueProcessing > 0 ? 'text-amber-600' : 'text-gray-900' }}">{{ number_format($queueProcessing) }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ __('settings.queue_processing') }}</p>
                    </div>
                    <div class="px-5 py-4 text-center">
                        <p class="text-2xl font-bold {{ $failedTotal > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ number_format($failedTotal) }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ __('settings.queue_failed') }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-gray-100">

                    {{-- Jobs by queue --}}
                    <div class="px-5 py-4">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">{{ __('settings.queue_by_queue') }}</p>
                        @if($queueByQueue->isEmpty())
                            <p class="text-sm text-gray-400">{{ __('settings.queue_empty') }}</p>
                        @else
                            <div class="space-y-2">
                                @foreach($queueByQueue as $q)
                                @php $qp = $queueTotal > 0 ? (int) min(100, round($q->cnt / $queueTotal * 100)) : 0; @endphp
                                <div>
                                    <div class="flex items-center justify-between mb-0.5">
                                        <span class="text-xs font-medium text-gray-700 font-mono">{{ $q->queue }}</span>
                                        <span class="text-xs text-gray-500">{{ number_format($q->cnt) }} {{ __('settings.queue_jobs_unit') }}</span>
                                    </div>
                                    <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                        <div class="h-1.5 bg-[#714B67] rounded-full" style="width: {{ $qp }}%"></div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Recent failures --}}
                    <div class="px-5 py-4">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">{{ __('settings.queue_recent_failures') }}</p>
                        @if($recentFailed->isEmpty())
                            <p class="text-sm text-gray-400">{{ __('settings.queue_no_failures') }}</p>
                        @else
                            <div class="space-y-2">
                                @foreach($recentFailed as $fail)
                                <div class="flex items-start justify-between gap-3 py-1.5 border-b border-gray-50 last:border-0">
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-gray-800 font-mono truncate">{{ $fail->queue }}</p>
                                        <p class="text-[11px] text-gray-400">{{ $fail->connection }}</p>
                                    </div>
                                    <p class="text-[11px] text-gray-400 shrink-0 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($fail->failed_at)->diffForHumans() }}
                                    </p>
                                </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                </div>
            </div>

        </div>{{-- end grid --}}
    </div>{{-- end inner --}}
</div>
@endsection
