<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\SkillType;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SkillTypeController extends Controller
{
    public function read(Request $request)
    {
        $this->authorize('viewAny', SkillType::class);

        $query = SkillType::query()->withCount(['skills', 'levels']);

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->where('active', false);
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        SortsTable::apply($query, $request);

        $skillTypes = $query->paginate(50)->withQueryString();

        return view('employees.skill-types.index', compact('skillTypes'));
    }

    public function show(SkillType $skillType)
    {
        $this->authorize('view', $skillType);

        $skillType->load(['skills', 'levels']);

        return view('employees.skill-types.show', compact('skillType'));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', SkillType::class);

        return view('employees.skill-types.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', SkillType::class);

        $data = $request->validate([
            'name'   => 'required|string|max:255',
            'active' => 'boolean',
            'skills'  => 'nullable|array',
            'skills.*.name' => 'required|string|max:255',
            'levels'  => 'nullable|array',
            'levels.*.name'           => 'required|string|max:255',
            'levels.*.level_progress' => 'required|integer|between:0,100',
            'levels.*.sequence'       => 'nullable|integer',
        ]);

        $skillsData = $data['skills'] ?? [];
        $levelsData = $data['levels'] ?? [];
        unset($data['skills'], $data['levels']);

        $skillType = DB::transaction(function () use ($data, $skillsData, $levelsData) {
            $skillType = SkillType::create($data);
            foreach ($skillsData as $skill) {
                $skillType->skills()->create(['name' => $skill['name']]);
            }
            foreach ($levelsData as $i => $level) {
                $skillType->levels()->create(array_merge($level, ['sequence' => $level['sequence'] ?? $i]));
            }
            return $skillType;
        });

        return redirect()->route('employees.skill-types.show', $skillType)->with('success', 'Skill type created.');
    }

    public function edit(SkillType $skillType)
    {
        $this->authorize('update', $skillType);

        $skillType->load(['skills', 'levels']);

        return view('employees.skill-types.edit', compact('skillType'));
    }

    public function write(Request $request, SkillType $skillType)
    {
        $this->authorize('update', $skillType);

        $data = $request->validate([
            'name'   => 'sometimes|required|string|max:255',
            'active' => 'boolean',
            'skills'  => 'nullable|array',
            'skills.*.name' => 'required|string|max:255',
            'levels'  => 'nullable|array',
            'levels.*.name'           => 'required|string|max:255',
            'levels.*.level_progress' => 'required|integer|between:0,100',
            'levels.*.sequence'       => 'nullable|integer',
        ]);

        $skillsData = $data['skills'] ?? null;
        $levelsData = $data['levels'] ?? null;
        unset($data['skills'], $data['levels']);

        DB::transaction(function () use ($skillType, $data, $skillsData, $levelsData) {
            $skillType->update($data);
            if ($skillsData !== null) {
                $skillType->skills()->delete();
                foreach ($skillsData as $skill) {
                    $skillType->skills()->create(['name' => $skill['name']]);
                }
            }
            if ($levelsData !== null) {
                $skillType->levels()->delete();
                foreach ($levelsData as $i => $level) {
                    $skillType->levels()->create(array_merge($level, ['sequence' => $level['sequence'] ?? $i]));
                }
            }
        });

        return redirect()->route('employees.skill-types.show', $skillType)->with('success', 'Skill type updated.');
    }

    public function archive(Request $_request, SkillType $skillType)
    {
        $this->authorize('update', $skillType);

        DB::transaction(fn () => $skillType->update(['active' => false]));

        return redirect()->route('employees.skill-types.index')->with('success', 'Skill type archived.');
    }

    public function unarchive(Request $_request, SkillType $skillType)
    {
        $this->authorize('update', $skillType);

        DB::transaction(fn () => $skillType->update(['active' => true]));

        return redirect()->route('employees.skill-types.show', $skillType)->with('success', 'Skill type restored.');
    }

    public function unlink(Request $_request, SkillType $skillType)
    {
        $this->authorize('delete', $skillType);

        DB::transaction(fn () => $skillType->delete());

        return redirect()->route('employees.skill-types.index')->with('success', 'Skill type deleted.');
    }

    public function addComment(Request $request, SkillType $skillType)
    {
        $this->authorize('comment', $skillType);
        $request->validate(['body' => 'required|string|max:5000']);
        $skillType->logComment($request->body);
        return back()->with('success', 'Comment added.');
    }
}
