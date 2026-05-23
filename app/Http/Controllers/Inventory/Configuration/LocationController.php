<?php

namespace App\Http\Controllers\Inventory\Configuration;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreLocationRequest;
use App\Http\Requests\Inventory\UpdateLocationRequest;
use App\Models\Inventory\Location;
use App\Services\Chatter\ChatterService;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly ChatterService $chatterService,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Location::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = Location::query()->with(['parent', 'company'])->forCompanies($activeCompanyIds);
        if ($request->query('filter') !== 'all') $query->active();
        if ($usage = $request->query('usage')) $query->where('usage', $usage);
        SearchFilters::apply($query, $request);

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(Location::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with(['parent', 'company'])->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('inventory.configuration.locations.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);
        $locations = $query->paginate(50)->withQueryString();
        return view('inventory.configuration.locations.index', compact('locations'));
    }

    public function show(Location $location)
    {
        $this->authorize('view', $location);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($location->company_id) || in_array($location->company_id, $activeCompanyIds), 403);
        $location->load(['parent', 'children', 'company', 'creator', 'updater']);

        $allIds = Location::active()->forCompanies($activeCompanyIds)->orderBy('complete_name')->pluck('id');
        $currentIndex   = $allIds->search($location->id);
        $prevId         = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId         = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal    = $allIds->count();

        return view('inventory.configuration.locations.show', compact('location', 'prevId', 'nextId', 'recordPosition', 'recordTotal'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Location::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;
        return view('inventory.configuration.locations.create', compact('defaultCompanyId'));
    }

    public function store(StoreLocationRequest $request)
    {
        $data = $request->validated();
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        if (!empty($data['company_id'])) {
            abort_unless(in_array($data['company_id'], $activeCompanyIds), 403);
        }
        $data['active']     = true;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $location = DB::transaction(function () use ($data) {
            $loc = Location::create($data);
            $loc->updateCompleteName();
            $this->chatterService->logCreated($loc, 'Location');
            return $loc;
        });
        return redirect()->route('inventory.config.locations.show', $location)->with('success', 'Location created.');
    }

    public function edit(Location $location)
    {
        $this->authorize('update', $location);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($location->company_id, $activeCompanyIds), 403);
        $location->load(['parent', 'company']);
        return view('inventory.configuration.locations.edit', compact('location'));
    }

    public function write(UpdateLocationRequest $request, Location $location)
    {
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($location->company_id, $activeCompanyIds), 403);
        $data = $request->validated();
        $data['updated_by'] = auth()->id();
        DB::transaction(function () use ($location, $data) {
            $location->update($data);
            $location->updateCompleteName();
        });
        return redirect()->route('inventory.config.locations.show', $location)->with('success', 'Location updated.');
    }

    public function archive(Request $_request, Location $location)
    {
        $this->authorize('update', $location);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($location->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $location->update(['active' => false, 'updated_by' => auth()->id()]));
        return redirect()->route('inventory.config.locations.index')->with('success', 'Location archived.');
    }

    public function unarchive(Request $_request, Location $location)
    {
        $this->authorize('update', $location);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($location->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $location->update(['active' => true, 'updated_by' => auth()->id()]));
        return redirect()->route('inventory.config.locations.show', $location)->with('success', 'Location restored.');
    }

    public function unlink(Request $_request, Location $location)
    {
        $this->authorize('delete', $location);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($location->company_id, $activeCompanyIds), 403);
        if ($location->children()->exists()) {
            return back()->with('error', 'Cannot delete a location with sub-locations.');
        }
        DB::transaction(fn () => $location->delete());
        return redirect()->route('inventory.config.locations.index')->with('success', 'Location deleted.');
    }

    public function addComment(Request $request, Location $location)
    {
        $this->authorize('comment', $location);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($location->company_id, $activeCompanyIds), 403);
        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $location->logComment($request->body));
        return back()->with('success', 'Comment added.');
    }
}
