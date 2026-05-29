<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\DepartureReason;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartureReasonController extends Controller
{
    public function read(Request $request)
    {
        $this->authorize('viewAny', DepartureReason::class);

        $query = DepartureReason::query();

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->where('active', false);
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(DepartureReason::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('employees.departure-reasons.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);

        $reasons = $query->paginate(50)->withQueryString();

        return view('employees.departure-reasons.index', compact('reasons'));
    }

    public function show(DepartureReason $departureReason)
    {
        $this->authorize('view', $departureReason);

        return view('employees.departure-reasons.show', compact('departureReason'));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', DepartureReason::class);

        return view('employees.departure-reasons.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', DepartureReason::class);

        $data = $request->validate([
            'name'   => 'required|string|max:255',
            'active' => 'boolean',
        ]);

        $departureReason = DB::transaction(fn () => DepartureReason::create($data));

        return redirect()->route('employees.departure-reasons.show', $departureReason)->with('success', __('employees.departure_reason_created'));
    }

    public function edit(DepartureReason $departureReason)
    {
        $this->authorize('update', $departureReason);

        return view('employees.departure-reasons.edit', compact('departureReason'));
    }

    public function write(Request $request, DepartureReason $departureReason)
    {
        $this->authorize('update', $departureReason);

        $data = $request->validate([
            'name'   => 'sometimes|required|string|max:255',
            'active' => 'boolean',
        ]);

        DB::transaction(fn () => $departureReason->update($data));

        return redirect()->route('employees.departure-reasons.show', $departureReason)->with('success', __('employees.departure_reason_updated'));
    }

    public function archive(Request $_request, DepartureReason $departureReason)
    {
        $this->authorize('update', $departureReason);

        DB::transaction(fn () => $departureReason->update(['active' => false]));

        return redirect()->route('employees.departure-reasons.index')->with('success', __('employees.departure_reason_archived'));
    }

    public function unarchive(Request $_request, DepartureReason $departureReason)
    {
        $this->authorize('update', $departureReason);

        DB::transaction(fn () => $departureReason->update(['active' => true]));

        return redirect()->route('employees.departure-reasons.show', $departureReason)->with('success', __('employees.departure_reason_unarchived'));
    }

    public function unlink(Request $_request, DepartureReason $departureReason)
    {
        $this->authorize('delete', $departureReason);

        DB::transaction(fn () => $departureReason->delete());

        return redirect()->route('employees.departure-reasons.index')->with('success', __('employees.departure_reason_deleted'));
    }

    public function addComment(Request $request, DepartureReason $departureReason)
    {
        $this->authorize('comment', $departureReason);
        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $departureReason->logComment($request->body));
        return back()->with('success', __('employees.comment_added'));
    }
}
