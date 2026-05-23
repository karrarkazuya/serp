<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\Security\Permission;
use App\Models\Security\Role;
use App\Models\Settings\Company;
use App\Models\Settings\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Setting::class);

        $settings = Setting::getGroup('general');

        return view('settings.index', compact('settings'));
    }

    public function write(Request $request)
    {
        $this->authorize('update', Setting::class);

        $validated = $request->validate([
            'company_name'             => 'nullable|string|max:255',
            'company_email'            => 'nullable|email|max:255',
            'company_phone'            => 'nullable|string|max:50',
            'company_website'          => 'nullable|url|max:255',
            'company_address'          => 'nullable|string|max:500',
            'language'                 => 'nullable|string|in:en,ar',
            'timezone'                 => 'nullable|timezone',
            'require_strong_passwords' => 'nullable|boolean',
            'session_timeout'          => 'nullable|integer|min:5|max:1440',
        ]);

        // Checkboxes are absent from the request when unchecked — force to 0
        $validated['require_strong_passwords'] = $request->boolean('require_strong_passwords') ? '1' : '0';

        foreach ($validated as $key => $value) {
            Setting::setValue($key, $value ?? '');
        }

        return back()->with('success', 'Settings saved successfully.');
    }

    public function system()
    {
        $this->authorize('viewAny', Setting::class);

        // ── Users ──────────────────────────────────────────────────────────────
        $totalUsers  = User::whereKeyNot(0)->count();
        $activeUsers = User::whereKeyNot(0)->where('active', true)->count();
        $onlineUsers = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
            ->distinct('user_id')
            ->count('user_id');

        // ── Storage (files table — Eloquent so soft-deleted records are excluded) ──
        $storageTotal = (int) File::sum('size');
        $storageFiles = (int) File::count();

        $storageByDisk = File::selectRaw('disk, COUNT(*) as cnt, SUM(size) as total')
            ->groupBy('disk')
            ->orderByDesc('total')
            ->get();

        // Categorise by mime type prefix in PHP — avoids CASE WHEN dialect issues
        $rawByMime = File::selectRaw('mime_type, COUNT(*) as cnt, SUM(size) as total')
            ->groupBy('mime_type')
            ->get();

        $storageByCategory = $rawByMime->groupBy(function ($row) {
            $mime = $row->mime_type ?? '';
            if (str_starts_with($mime, 'image/'))       return 'Images';
            if ($mime === 'application/pdf')             return 'PDFs';
            if (str_starts_with($mime, 'text/') || $mime === 'application/csv') return 'Text / CSV';
            return 'Documents';
        })->map(fn ($rows, $label) => (object)[
            'label' => $label,
            'cnt'   => $rows->sum('cnt'),
            'total' => $rows->sum('total'),
        ])->sortByDesc('total')->values();

        // ── App-level counts ───────────────────────────────────────────────────
        $totalCompanies   = Company::count();
        $totalRoles       = Role::count();
        $totalPermissions = Permission::count();

        // ── Database size (SQLite) ─────────────────────────────────────────────
        $dbPath = database_path('database.sqlite');
        $dbSize = is_file($dbPath) ? filesize($dbPath) : null;

        // ── Server disk ────────────────────────────────────────────────────────
        $diskTotal = @disk_total_space(storage_path()) ?: null;
        $diskFree  = @disk_free_space(storage_path())  ?: null;

        // ── CPU / RAM ─────────────────────────────────────────────────────────
        $cpuCores = $this->detectCpuCores();
        $ramBytes = $this->detectRamBytes();

        // ── Queue / Jobs ──────────────────────────────────────────────────────
        $queuePending    = DB::table('jobs')->whereNull('reserved_at')->count();
        $queueProcessing = DB::table('jobs')->whereNotNull('reserved_at')->count();
        $queueTotal      = $queuePending + $queueProcessing;

        $queueByQueue = DB::table('jobs')
            ->selectRaw('queue, COUNT(*) as cnt')
            ->groupBy('queue')
            ->orderByDesc('cnt')
            ->get();

        $failedTotal = DB::table('failed_jobs')->count();

        $recentFailed = DB::table('failed_jobs')
            ->select('uuid', 'queue', 'connection', 'failed_at')
            ->orderByDesc('failed_at')
            ->limit(5)
            ->get();

        // ── PHP / Framework ───────────────────────────────────────────────────
        $phpVersion     = PHP_VERSION;
        $laravelVersion = app()->version();
        $phpOs          = PHP_OS_FAMILY . ' (' . php_uname('r') . ')';
        $appEnv         = config('app.env');
        $appDebug       = config('app.debug');
        $timezone       = config('app.timezone');
        $sessionDriver  = config('session.driver');
        $cacheDriver    = config('cache.default');
        $dbDriver       = DB::connection()->getDriverName();

        return view('settings.system', compact(
            'totalUsers', 'activeUsers', 'onlineUsers',
            'storageTotal', 'storageFiles', 'storageByDisk', 'storageByCategory',
            'totalCompanies', 'totalRoles', 'totalPermissions',
            'dbSize', 'diskTotal', 'diskFree',
            'cpuCores', 'ramBytes',
            'phpVersion', 'laravelVersion', 'phpOs',
            'appEnv', 'appDebug', 'timezone',
            'sessionDriver', 'cacheDriver', 'dbDriver',
            'queuePending', 'queueProcessing', 'queueTotal', 'queueByQueue',
            'failedTotal', 'recentFailed'
        ));
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function detectCpuCores(): string
    {
        if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/cpuinfo')) {
            $count = substr_count((string) @file_get_contents('/proc/cpuinfo'), 'processor');
            return $count > 0 ? (string) $count : 'N/A';
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            $result = @shell_exec('sysctl -n hw.logicalcpu 2>/dev/null');
            return $result ? trim($result) : 'N/A';
        }

        return 'N/A';
    }

    private function detectRamBytes(): ?int
    {
        if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/meminfo')) {
            preg_match('/MemTotal:\s+(\d+)\s+kB/', (string) @file_get_contents('/proc/meminfo'), $m);
            return isset($m[1]) ? (int) $m[1] * 1024 : null;
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            $result = @shell_exec('sysctl -n hw.memsize 2>/dev/null');
            return $result ? (int) trim($result) : null;
        }

        return null;
    }
}
