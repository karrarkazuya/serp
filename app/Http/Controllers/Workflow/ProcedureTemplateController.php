<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Requests\Workflow\StoreProcedureTemplateRequest;
use App\Models\Employees\Department;
use App\Models\Workflow\Group;
use App\Models\Workflow\ProcedureStep;
use App\Models\Workflow\ProcedureTemplate;
use App\Services\Workflow\FlowchartService;
use App\Services\Workflow\WorkflowConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcedureTemplateController extends Controller
{
    public function __construct(
        private readonly WorkflowConfigService $configService,
        private readonly FlowchartService      $flowchartService,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', ProcedureTemplate::class);
        $query = ProcedureTemplate::query()->with(['defaultGroup']);
        SearchFilters::apply($query, $request);
        SortsTable::apply($query, $request);
        $templates = $query->paginate(20)->withQueryString();

        return view('workflow.configuration.procedure-templates.index', compact('templates'));
    }

    public function show(ProcedureTemplate $procedureTemplate)
    {
        $this->authorize('view', $procedureTemplate);
        $procedureTemplate->load(['defaultGroup', 'departments', 'steps.inputs.options', 'steps.nextSteps', 'steps.defaultDepartment']);
        $flowchartUrl = route('workflow.config.procedure-templates.flowchart', $procedureTemplate);
        return view('workflow.configuration.procedure-templates.show', compact('procedureTemplate', 'flowchartUrl'));
    }

    public function flowchart(Request $request, ProcedureTemplate $procedureTemplate)
    {
        $this->authorize('view', $procedureTemplate);
        $procedureTemplate->load(['steps.nextSteps', 'steps.pathChoices.targetStep', 'steps.subProcedures', 'steps.defaultDepartment']);
        $payload   = $this->flowchartService->buildPayload($procedureTemplate);
        $mode      = in_array($request->query('mode'), ['show', 'edit']) ? $request->query('mode') : 'show';

        if ($mode === 'edit') {
            $this->authorize('update', $procedureTemplate);
            $tplId         = $procedureTemplate->id;
            $payload['nodes'] = array_map(function ($node) use ($tplId) {
                if (is_int($node['id'])) {
                    $node['edit_url']   = route('workflow.config.procedure-templates.steps.edit',    [$tplId, $node['id']]);
                    $node['delete_url'] = route('workflow.config.procedure-templates.steps.destroy', [$tplId, $node['id']]);
                }
                return $node;
            }, $payload['nodes']);
        }

        $saveUrl   = route('workflow.config.procedure-templates.flowchart.layout.save', $procedureTemplate);
        $resetUrl  = route('workflow.config.procedure-templates.flowchart.layout.reset', $procedureTemplate);
        $csrfToken = csrf_token();
        return view('workflow.configuration.procedure-templates.flowchart',
            compact('procedureTemplate', 'payload', 'saveUrl', 'resetUrl', 'csrfToken', 'mode'));
    }

    public function saveFlowchartLayout(Request $request, ProcedureTemplate $procedureTemplate): JsonResponse
    {
        $this->authorize('update', $procedureTemplate);
        $positions = $request->input('positions', []);
        if (!is_array($positions)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid positions'], 400);
        }

        $stepIds    = $procedureTemplate->steps()->pluck('id')->flip();
        $subUpdates = [];

        DB::transaction(function () use ($positions, $stepIds, $procedureTemplate, &$subUpdates) {
            foreach ($positions as $pos) {
                if (!is_array($pos)) continue;
                $rawId = $pos['id'] ?? null;
                $x     = max((int) round((float) ($pos['x'] ?? 0)), 0);
                $y     = max((int) round((float) ($pos['y'] ?? 0)), 0);

                if (is_string($rawId) && str_starts_with($rawId, 'subproc-')) {
                    $subId = (int) substr($rawId, 8);
                    if ($subId > 0) {
                        $subUpdates[$subId] = ['x' => $x, 'y' => $y];
                    }
                    continue;
                }

                $id = (int) $rawId;
                if (!$stepIds->has($id)) continue;
                ProcedureStep::where('id', $id)->update([
                    'flowchart_position_saved' => true,
                    'flowchart_x'              => $x,
                    'flowchart_y'              => $y,
                ]);
            }

            if (!empty($subUpdates)) {
                $merged = array_replace($procedureTemplate->flowchart_sub_positions ?? [], $subUpdates);
                $procedureTemplate->update(['flowchart_sub_positions' => $merged]);
            }
        });

        return response()->json(['status' => 'success']);
    }

    public function resetFlowchartLayout(ProcedureTemplate $procedureTemplate): JsonResponse
    {
        $this->authorize('update', $procedureTemplate);

        DB::transaction(function () use ($procedureTemplate) {
            $procedureTemplate->steps()->update([
                'flowchart_position_saved' => false,
                'flowchart_x'              => 0,
                'flowchart_y'              => 0,
            ]);
            $procedureTemplate->update(['flowchart_sub_positions' => null]);
        });

        return response()->json(['status' => 'success']);
    }

    public function create()
    {
        $this->authorize('create', ProcedureTemplate::class);
        $groups = Group::where('active', true)->orderBy('name')->get();
        $departments = Department::where('active', true)->orderBy('name')->get();

        return view('workflow.configuration.procedure-templates.create', compact('groups', 'departments'));
    }

    public function store(StoreProcedureTemplateRequest $request)
    {
        $data = $request->validated();
        $deptIds = $data['departments'] ?? [];
        unset($data['departments']);

        $tpl = DB::transaction(fn () => $this->configService->createProcedureTemplate($data, $deptIds));

        return redirect()->route('workflow.config.procedure-templates.show', $tpl)->with('success', 'Procedure template created.');
    }

    public function edit(ProcedureTemplate $procedureTemplate)
    {
        $this->authorize('update', $procedureTemplate);
        $procedureTemplate->load(['departments', 'steps.nextSteps', 'steps.defaultDepartment', 'steps.inputs']);
        $groups       = Group::where('active', true)->orderBy('name')->get();
        $flowchartUrl = route('workflow.config.procedure-templates.flowchart', $procedureTemplate);

        return view('workflow.configuration.procedure-templates.edit', compact('procedureTemplate', 'groups', 'flowchartUrl'));
    }

    public function write(Request $request, ProcedureTemplate $procedureTemplate)
    {
        $this->authorize('update', $procedureTemplate);
        $data = $request->validate([
            'name'                 => 'required|string|max:255',
            'description'          => 'nullable|string|max:5000',
            'default_group_id'     => 'nullable|exists:workflow_groups,id',
            'creator_see_tasks'    => 'boolean',
            'enabled'              => 'boolean',
            'departments'          => 'nullable|array',
            'departments.*'        => 'exists:hr_departments,id',
        ]);
        $deptIds = $data['departments'] ?? [];
        unset($data['departments']);

        DB::transaction(fn () => $this->configService->updateProcedureTemplate($procedureTemplate, $data, $deptIds));

        return redirect()->route('workflow.config.procedure-templates.show', $procedureTemplate)->with('success', 'Procedure template updated.');
    }

    public function unlink(ProcedureTemplate $procedureTemplate)
    {
        $this->authorize('delete', $procedureTemplate);
        DB::transaction(fn () => $this->configService->deleteProcedureTemplate($procedureTemplate));

        return redirect()->route('workflow.config.procedure-templates.index')->with('success', 'Procedure template deleted.');
    }

    public function storeStep(Request $request, ProcedureTemplate $procedureTemplate)
    {
        $this->authorize('update', $procedureTemplate);
        $data = $request->validate([
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string|max:5000',
            'default_department_id' => 'nullable|exists:hr_departments,id',
            'is_approve_only'       => 'boolean',
            'has_procedures'        => 'boolean',
            'procedures_required'   => 'boolean',
            'ignore_state'          => 'boolean',
            'has_path_choice'       => 'boolean',
            'path_choice_question'  => 'nullable|string|max:500',
            'path_choice_required'  => 'boolean',
            'enabled'               => 'boolean',
            'next_step_ids'         => 'nullable|array',
            'next_step_ids.*'       => 'exists:workflow_procedure_steps,id',
            'sub_procedure_ids'     => 'nullable|array',
            'sub_procedure_ids.*'   => 'exists:workflow_procedure_templates,id',
        ]);
        $nextStepIds      = $data['next_step_ids'] ?? [];
        $subProcedureIds  = $data['sub_procedure_ids'] ?? [];
        unset($data['next_step_ids'], $data['sub_procedure_ids']);

        // Ensure all next steps belong to this template
        if (!empty($nextStepIds)) {
            $validCount = $procedureTemplate->steps()->whereIn('id', $nextStepIds)->count();
            abort_if($validCount !== count($nextStepIds), 422);
        }

        // Prevent direct self-reference in sub-procedures (would cause infinite recursion on instantiation)
        abort_if(in_array($procedureTemplate->id, $subProcedureIds, true), 422, 'A template cannot reference itself as a sub-procedure.');

        DB::transaction(function () use ($procedureTemplate, $data, $nextStepIds, $subProcedureIds) {
            $step = $procedureTemplate->steps()->create($data);
            $step->nextSteps()->sync($nextStepIds);
            $step->subProcedures()->sync($subProcedureIds);
        });

        if ($request->expectsJson()) {
            return response()->json(['status' => 'success']);
        }

        return redirect()->route('workflow.config.procedure-templates.edit', $procedureTemplate)
            ->with('success', 'Step added.');
    }

    public function editStep(ProcedureTemplate $procedureTemplate, ProcedureStep $step)
    {
        $this->authorize('update', $procedureTemplate);
        abort_if($step->procedure_template_id !== $procedureTemplate->id, 404);
        $step->load(['inputs.options', 'nextSteps', 'defaultDepartment', 'pathChoices', 'subProcedures']);
        $siblings             = $procedureTemplate->steps()->where('id', '!=', $step->id)->orderBy('id')->get();
        $availableSubProcs    = \App\Models\Workflow\ProcedureTemplate::where('enabled', true)
            ->where('id', '!=', $procedureTemplate->id)
            ->orderBy('name')->get();

        return view('workflow.configuration.procedure-templates.step-edit',
            compact('procedureTemplate', 'step', 'siblings', 'availableSubProcs'));
    }

    public function updateStep(Request $request, ProcedureTemplate $procedureTemplate, ProcedureStep $step)
    {
        $this->authorize('update', $procedureTemplate);
        abort_if($step->procedure_template_id !== $procedureTemplate->id, 404);

        $data = $request->validate([
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string|max:5000',
            'default_department_id' => 'nullable|exists:hr_departments,id',
            'is_approve_only'          => 'boolean',
            'has_procedures'           => 'boolean',
            'procedures_required'      => 'boolean',
            'ignore_state'             => 'boolean',
            'has_path_choice'          => 'boolean',
            'path_choice_question'     => 'nullable|string|max:500',
            'path_choice_required'     => 'boolean',
            'enabled'                  => 'boolean',
            'next_step_ids'            => 'nullable|array',
            'next_step_ids.*'          => 'exists:workflow_procedure_steps,id',
            'sub_procedure_ids'        => 'nullable|array',
            'sub_procedure_ids.*'      => 'exists:workflow_procedure_templates,id',
            'path_choice_names'        => 'nullable|array',
            'path_choice_names.*'      => 'nullable|string|max:255',
            'inputs'                   => 'nullable|array',
            'inputs.*.id'              => 'nullable|integer',
            'inputs.*.name'            => 'required_with:inputs.*|string|max:255',
            'inputs.*.type'            => 'required_with:inputs.*|string|in:char,int,float,date,datetime,boolean,select,multiselect,textarea,file,label',
            'inputs.*.is_required'     => 'nullable|boolean',
            'inputs.*.sort_order'      => 'nullable|integer',
            'inputs.*.options'         => 'nullable|string',
        ]);
        $nextStepIds      = $data['next_step_ids'] ?? [];
        $subProcedureIds  = $data['sub_procedure_ids'] ?? [];
        $pathChoiceNames  = $data['path_choice_names'] ?? [];
        $inputsData       = $data['inputs'] ?? [];
        unset($data['next_step_ids'], $data['sub_procedure_ids'], $data['path_choice_names'], $data['inputs']);

        // Ensure all next steps belong to this template (exclude current step from valid pool)
        if (!empty($nextStepIds)) {
            $validCount = $procedureTemplate->steps()->where('id', '!=', $step->id)->whereIn('id', $nextStepIds)->count();
            abort_if($validCount !== count($nextStepIds), 422);
        }

        // Prevent direct self-reference in sub-procedures
        abort_if(in_array($procedureTemplate->id, $subProcedureIds, true), 422, 'A template cannot reference itself as a sub-procedure.');

        DB::transaction(function () use ($step, $data, $nextStepIds, $subProcedureIds, $pathChoiceNames, $inputsData) {
            $step->update($data);
            $step->nextSteps()->sync($nextStepIds);
            $step->subProcedures()->sync($subProcedureIds);

            // Rebuild path choice records from the submitted names (only for checked next steps)
            $step->pathChoices()->delete();
            if ($step->has_path_choice) {
                foreach ($nextStepIds as $targetId) {
                    $name = trim($pathChoiceNames[$targetId] ?? '');
                    $step->pathChoices()->create([
                        'target_step_id' => $targetId,
                        'name'           => $name ?: null,
                    ]);
                }
            }

            $this->configService->syncProcedureStepInputs($step, $inputsData);
        });

        $redirectTo = route('workflow.config.procedure-templates.steps.edit', [$procedureTemplate, $step]);
        if ($request->query('frame')) {
            $redirectTo .= '?frame=1';
        }

        return redirect($redirectTo)->with('success', 'Step saved.');
    }

    public function destroyStep(Request $request, ProcedureTemplate $procedureTemplate, ProcedureStep $step)
    {
        $this->authorize('update', $procedureTemplate);
        abort_if($step->procedure_template_id !== $procedureTemplate->id, 404);

        DB::transaction(function () use ($step) {
            $step->inputs()->delete();
            $step->nextSteps()->detach();
            $step->previousSteps()->detach();
            $step->delete();
        });

        if ($request->expectsJson()) {
            return response()->json(['status' => 'success']);
        }

        return redirect()->route('workflow.config.procedure-templates.edit', $procedureTemplate)
            ->with('success', 'Step deleted.');
    }

    public function stepsLookup(Request $request, ProcedureTemplate $procedureTemplate): JsonResponse
    {
        $this->authorize('update', $procedureTemplate);

        $perPage = max(1, min((int) $request->integer('per_page', 8), 50));
        $search  = (string) $request->query('search', '');
        $exclude = collect((array) $request->query('exclude', []))
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => is_numeric($v) ? (int) $v : $v)
            ->all();

        $steps = $procedureTemplate->steps()
            ->when(!empty($exclude), fn ($q) => $q->whereNotIn('id', $exclude))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('id')
            ->paginate($perPage);

        $steps->getCollection()->transform(fn ($step) => [
            'id'    => $step->id,
            'label' => $step->name,
            'color' => null,
        ]);

        return response()->json($steps);
    }

    public function addComment(Request $request, ProcedureTemplate $procedureTemplate)
    {
        $this->authorize('comment', $procedureTemplate);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $procedureTemplate->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }
}
