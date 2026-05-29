<?php

namespace App\Http\Controllers;

use App\Services\ImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    /**
     * MIME types we accept for upload. Validated server-side; the file picker
     * `accept=` attribute is only a UX hint.
     */
    private const ACCEPTED_MIMES = [
        'csv', 'txt', 'xlsx', 'xls',
    ];

    public function __construct(
        private readonly ImportService $importService,
    ) {}

    public function template(Request $request, string $modelKey): StreamedResponse
    {
        $config = $this->resolveConfig($modelKey);
        $this->authorizeImport($request, $config);

        $format = in_array($request->query('format'), ['csv', 'xlsx'], true)
            ? (string) $request->query('format')
            : 'xlsx';

        return $this->importService->template($config, $format);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'model' => 'required|string',
            // Hard MIME + extension guard. We never trust client-reported types alone.
            'file'  => [
                'required',
                'file',
                'max:10240',
                'mimes:'.implode(',', self::ACCEPTED_MIMES),
            ],
            'redirect' => 'nullable|string|max:500',
        ]);

        $modelKey = (string) $request->input('model');
        $config   = $this->resolveConfig($modelKey);
        $this->authorizeImport($request, $config);

        $redirect = $this->safeRedirect($request->input('redirect'));

        try {
            $parsed = $this->importService->parse($request->file('file'));
        } catch (\Throwable $e) {
            return redirect($redirect)->with('error', $e->getMessage());
        }

        if (empty($parsed['rows'])) {
            return redirect($redirect)->with('error', __('common.import_no_rows'));
        }

        try {
            $result = DB::transaction(fn () => $this->importService->processRows($parsed['rows'], $config));
        } catch (\Throwable $e) {
            return redirect($redirect)
                ->with('error', __('common.import_rolled_back', ['error' => $e->getMessage()]));
        }

        $count = $result['imported'] ?? 0;
        return redirect($redirect)->with('success', trans_choice('common.import_success', $count, ['count' => $count]));
    }

    private function resolveConfig(string $modelKey): array
    {
        $importable = (array) config('importable', []);
        $config     = $importable[$modelKey] ?? null;
        abort_unless(is_array($config), 404, 'Unknown import model.');
        return $config;
    }

    private function authorizeImport(Request $request, array $config): void
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->hasPermission($config['permission'] ?? '_no_such_permission'), 403);
    }

    /**
     * Only allow redirects back to a same-origin internal path. Anything else
     * falls through to the app root. This is a defence against open-redirect
     * payloads in the `redirect` form input.
     */
    private function safeRedirect(?string $candidate): string
    {
        if (!is_string($candidate) || $candidate === '') {
            return url('/');
        }
        if (!str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
            return url('/');
        }
        return $candidate;
    }
}
