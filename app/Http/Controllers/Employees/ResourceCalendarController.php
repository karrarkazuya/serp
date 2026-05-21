<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\ResourceCalendar;
use App\Services\Company\CompanyContextService;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ResourceCalendarController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', \App\Models\Employees\Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = ResourceCalendar::query()->with('company');

        if (!empty($activeCompanyIds)) {
            $query->forCompanies($activeCompanyIds);
        }

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->where('active', false);
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        SortsTable::apply($query, $request);

        $schedules = $query->withCount('employees')->paginate(50)->withQueryString();

        return view('employees.schedules.index', compact('schedules'));
    }

    public function show(ResourceCalendar $schedule)
    {
        $this->authorize('viewAny', \App\Models\Employees\Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($schedule->company_id, $activeCompanyIds), 403);

        $schedule->load(['company', 'attendances', 'employees.job', 'employees.department']);

        return view('employees.schedules.show', compact('schedule'));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', \App\Models\Employees\Employee::class);

        return view('employees.schedules.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', \App\Models\Employees\Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $companyRule      = Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds);

        $data = $request->validate([
            'name'                    => 'required|string|max:255',
            'timezone'                => 'nullable|string|max:100',
            'hours_per_day'           => 'nullable|numeric|min:0|max:24',
            'company_hours_per_week'  => 'nullable|numeric|min:0|max:168',
            'flexible_hours'          => 'boolean',
            'active'                  => 'boolean',
            'company_id'              => ['nullable', $companyRule],
            'attendances'             => 'nullable|array',
            'attendances.*.day_of_week' => 'required|integer|between:0,6',
            'attendances.*.hour_from'   => 'required|string',
            'attendances.*.hour_to'     => 'required|string',
            'attendances.*.next_day'    => 'nullable|boolean',
            'attendances.*.sequence'    => 'nullable|integer',
        ]);

        $attendancesData = $data['attendances'] ?? [];
        unset($data['attendances']);

        foreach ($attendancesData as &$att) {
            $from    = $this->timeToDecimal($att['hour_from'] ?? '00:00');
            $to      = $this->timeToDecimal($att['hour_to']   ?? '00:00');
            $nextDay = !empty($att['next_day']);
            $att['hour_from']   = $from;
            $att['hour_to']     = $to + ($nextDay ? 24 : 0);
            $att['day_period']  = $this->computePeriod($from);
            unset($att['next_day']);
        }
        unset($att);

        $schedule = DB::transaction(function () use ($data, $attendancesData) {
            $schedule = ResourceCalendar::create($data);
            foreach ($attendancesData as $i => $att) {
                $schedule->attendances()->create(array_merge($att, ['sequence' => $att['sequence'] ?? $i]));
            }
            return $schedule;
        });

        return redirect()->route('employees.schedules.show', $schedule)->with('success', 'Working schedule created.');
    }

    public function edit(ResourceCalendar $schedule)
    {
        $this->authorize('update', \App\Models\Employees\Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($schedule->company_id, $activeCompanyIds), 403);

        $schedule->load(['attendances', 'employees.job']);

        return view('employees.schedules.edit', compact('schedule'));
    }

    public function write(Request $request, ResourceCalendar $schedule)
    {
        $this->authorize('update', \App\Models\Employees\Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($schedule->company_id, $activeCompanyIds), 403);

        $companyRule = Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds);

        $data = $request->validate([
            'name'                    => 'sometimes|required|string|max:255',
            'timezone'                => 'nullable|string|max:100',
            'hours_per_day'           => 'nullable|numeric|min:0|max:24',
            'company_hours_per_week'  => 'nullable|numeric|min:0|max:168',
            'flexible_hours'          => 'boolean',
            'active'                  => 'boolean',
            'company_id'              => ['nullable', $companyRule],
            'attendances'             => 'nullable|array',
            'attendances.*.day_of_week' => 'required|integer|between:0,6',
            'attendances.*.hour_from'   => 'required|string',
            'attendances.*.hour_to'     => 'required|string',
            'attendances.*.next_day'    => 'nullable|boolean',
            'attendances.*.sequence'    => 'nullable|integer',
        ]);

        $attendancesData = $data['attendances'] ?? null;
        unset($data['attendances']);

        if ($attendancesData !== null) {
            foreach ($attendancesData as &$att) {
                $from    = $this->timeToDecimal($att['hour_from'] ?? '00:00');
                $to      = $this->timeToDecimal($att['hour_to']   ?? '00:00');
                $nextDay = !empty($att['next_day']);
                $att['hour_from']   = $from;
                $att['hour_to']     = $to + ($nextDay ? 24 : 0);
                $att['day_period']  = $this->computePeriod($from);
                unset($att['next_day']);
            }
            unset($att);
        }

        DB::transaction(function () use ($schedule, $data, $attendancesData) {
            $schedule->update($data);
            if ($attendancesData !== null) {
                $schedule->attendances()->delete();
                foreach ($attendancesData as $i => $att) {
                    $schedule->attendances()->create(array_merge($att, ['sequence' => $att['sequence'] ?? $i]));
                }
            }
        });

        return redirect()->route('employees.schedules.show', $schedule)->with('success', 'Working schedule updated.');
    }

    public function archive(Request $_request, ResourceCalendar $schedule)
    {
        $this->authorize('update', \App\Models\Employees\Employee::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($schedule->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $schedule->update(['active' => false]));

        return redirect()->route('employees.schedules.index')->with('success', 'Working schedule archived.');
    }

    public function unarchive(Request $_request, ResourceCalendar $schedule)
    {
        $this->authorize('update', \App\Models\Employees\Employee::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($schedule->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $schedule->update(['active' => true]));

        return redirect()->route('employees.schedules.show', $schedule)->with('success', 'Working schedule restored.');
    }

    public function unlink(Request $_request, ResourceCalendar $schedule)
    {
        $this->authorize('delete', \App\Models\Employees\Employee::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($schedule->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $schedule->delete());

        return redirect()->route('employees.schedules.index')->with('success', 'Working schedule deleted.');
    }

    public function addComment(Request $request, ResourceCalendar $schedule)
    {
        $this->authorize('update', \App\Models\Employees\Employee::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($schedule->company_id, $activeCompanyIds), 403);

        $request->validate(['body' => 'required|string|max:5000']);
        $schedule->logComment($request->body);

        return back()->with('success', 'Comment added.');
    }

    private function timeToDecimal(string $time): float
    {
        [$h, $m] = explode(':', $time . ':00');
        return (int)$h + ((int)$m / 60);
    }

    private function computePeriod(float $hourFrom): string
    {
        return match(true) {
            $hourFrom < 12 => 'morning',
            $hourFrom < 17 => 'afternoon',
            default        => 'evening',
        };
    }
}
