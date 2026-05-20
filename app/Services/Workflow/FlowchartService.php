<?php

namespace App\Services\Workflow;

use App\Models\Workflow\ProcedureTemplate;

class FlowchartService
{
    private const NODE_W    = 260;
    private const NODE_H    = 96;
    private const H_GAP     = 120;
    private const V_GAP     = 150;
    private const PAD_X     = 80;
    private const PAD_Y     = 56;
    private const SUB_W     = 220;
    private const SUB_H     = 90;
    private const SUB_GAP   = 36;
    private const SUB_LANE  = 180;

    public function buildPayload(ProcedureTemplate $template): array
    {
        $steps   = $template->steps->where('active', true)->sortBy('id')->values();
        $stepIds = $steps->pluck('id')->flip();
        $byId    = $steps->keyBy('id');

        $edges               = [];
        $seen                = [];
        $incoming            = $steps->mapWithKeys(fn ($s) => [$s->id => []])->toArray();
        $outgoing            = $steps->mapWithKeys(fn ($s) => [$s->id => []])->toArray();
        $graph               = $steps->mapWithKeys(fn ($s) => [$s->id => []])->toArray();
        $choiceTargetSources = $steps->mapWithKeys(fn ($s) => [$s->id => []])->toArray();
        $subSources          = [];

        $addEdge = function (
            int|string $from,
            int|string $to,
            ?string    $label  = null,
            bool       $choice = false,
            string     $kind   = 'task'
        ) use (&$edges, &$seen): void {
            $key = "{$from}|{$to}|{$label}|" . ($choice ? '1' : '0') . "|{$kind}";
            if (isset($seen[$key])) return;
            $seen[$key] = true;
            $edges[]    = ['from' => $from, 'to' => $to, 'label' => $label, 'choice' => $choice, 'kind' => $kind];
        };

        foreach ($steps as $step) {
            $choiceTargetIds = [];

            foreach ($step->pathChoices->sortBy('id') as $choice) {
                $target = $choice->targetStep;
                if (!$target || !$stepIds->has($target->id)) continue;
                $choiceTargetIds[]                      = $target->id;
                $incoming[$target->id][]                = $step->id;
                $outgoing[$step->id][]                  = $target->id;
                $choiceTargetSources[$target->id][]     = $step->id;
                if (!in_array($target->id, $graph[$step->id], true)) {
                    $graph[$step->id][] = $target->id;
                }
                $addEdge($step->id, $target->id, $choice->name ?: null, true);
            }

            foreach ($step->nextSteps->sortBy('id') as $next) {
                if (!$stepIds->has($next->id)) continue;
                if (in_array($next->id, $choiceTargetIds, true)) continue;
                $incoming[$next->id][]  = $step->id;
                $outgoing[$step->id][]  = $next->id;
                if (!in_array($next->id, $graph[$step->id], true)) {
                    $graph[$step->id][] = $next->id;
                }
                $addEdge($step->id, $next->id);
            }

            if ($step->has_procedures) {
                foreach ($step->subProcedures->sortBy('id') as $sub) {
                    $subId = "subproc-{$sub->id}";
                    $subSources[$subId] ??= [
                        'id'              => $subId,
                        'label'           => $sub->name ?: 'Untitled Sub Procedure',
                        'template_id'     => $sub->id,
                        'source_task_ids' => [],
                    ];
                    $subSources[$subId]['source_task_ids'][] = $step->id;
                    $addEdge($step->id, $subId, null, false, 'subprocedure');
                }
            }
        }

        // BFS topological sort → graph levels
        $levels   = [];
        $indegree = $steps->mapWithKeys(fn ($s) => [$s->id => count($incoming[$s->id])])->toArray();

        $startIds  = $steps->filter(fn ($s) => empty($incoming[$s->id]))->pluck('id')->sort()->values();
        $queue     = $startIds->toArray();
        $queued    = array_flip($queue);
        $done      = [];
        $doneSet   = [];

        foreach ($startIds as $id) {
            $levels[$id] = 0;
        }

        $choiceLevel = function (int $targetId) use (&$levels, $choiceTargetSources): ?int {
            $max = null;
            foreach ($choiceTargetSources[$targetId] as $srcId) {
                if (!isset($levels[$srcId])) continue;
                $max = $max === null ? $levels[$srcId] + 1 : max($max, $levels[$srcId] + 1);
            }
            return $max;
        };

        while (!empty($queue)) {
            $id = array_shift($queue);
            unset($queued[$id]);
            if (isset($doneSet[$id])) continue;
            $done[]      = $id;
            $doneSet[$id] = true;
            $cur          = $levels[$id] ?? 0;

            foreach ($graph[$id] as $nextId) {
                $forced = $choiceLevel($nextId);
                if ($forced !== null) {
                    $levels[$nextId] = $forced;
                } else {
                    $levels[$nextId] = max($levels[$nextId] ?? 0, $cur + 1);
                }
                $indegree[$nextId]--;
                if ($indegree[$nextId] <= 0 && !isset($queued[$nextId]) && !isset($doneSet[$nextId])) {
                    $queue[]         = $nextId;
                    $queued[$nextId] = true;
                }
            }
        }

        // Remaining (cycles)
        foreach ($steps->filter(fn ($s) => !isset($doneSet[$s->id]))->pluck('id')->sort() as $id) {
            $forced = $choiceLevel($id);
            if ($forced !== null) {
                $levels[$id] = $forced;
            } else {
                $parentLevels = array_map(fn ($pid) => $levels[$pid] ?? 0, $incoming[$id]);
                $levels[$id]  = $parentLevels ? max($parentLevels) + 1 : 0;
            }
            $done[] = $id;
        }

        // Group by level
        $byLevel = [];
        foreach ($done as $id) {
            $byLevel[$levels[$id] ?? 0][] = $byId[$id];
        }
        ksort($byLevel);

        // Node metadata
        $meta = [];
        foreach ($steps as $step) {
            $isStart  = empty($incoming[$step->id]);
            $isEnd    = empty($outgoing[$step->id]) && !empty($incoming[$step->id]);
            $isChoice = (bool) $step->has_path_choice;

            $type = match (true) {
                $isChoice && $isStart => 'start_choice',
                $isChoice             => 'choice',
                $isStart              => 'start',
                $isEnd                => 'end',
                default               => 'task',
            };

            $badges = [];
            if ($isStart)           $badges[] = 'Start';
            if ($isEnd)             $badges[] = 'End';
            if ($isChoice)          $badges[] = 'Decision';
            if ($step->has_procedures) $badges[] = 'Sub Procedures: ' . $step->subProcedures->count();

            $nextStepNames = [];
            foreach ($outgoing[$step->id] ?? [] as $nid) {
                $ns = $byId[$nid] ?? null;
                if ($ns) $nextStepNames[] = $ns->name;
            }

            $meta[$step->id] = [
                'id'          => $step->id,
                'label'       => $step->name ?: 'Untitled Step',
                'type'        => $type,
                'tag'         => null,
                'badges'      => $badges,
                'width'       => self::NODE_W,
                'height'      => self::NODE_H,
                'description' => $step->description ?: null,
                'department'  => $step->defaultDepartment?->name,
                'sla'         => $step->resolve_max_duration,
                'next_steps'  => $nextStepNames,
            ];
        }

        // Auto-layout widths
        $maxRowW = 0;
        foreach ($byLevel as $row) {
            $rw = array_sum(array_map(fn ($s) => $meta[$s->id]['width'], $row));
            $rw += max(count($row) - 1, 0) * self::H_GAP;
            $maxRowW = max($maxRowW, $rw);
        }
        $innerW = max($maxRowW, self::NODE_W);

        // Build node list with positions
        $nodes = [];
        foreach ($byLevel as $rowIdx => $row) {
            $rw      = array_sum(array_map(fn ($s) => $meta[$s->id]['width'], $row));
            $rw     += max(count($row) - 1, 0) * self::H_GAP;
            $startX  = self::PAD_X + max(($innerW - $rw) / 2, 0);
            $cursorX = $startX;

            foreach ($row as $step) {
                $m    = $meta[$step->id];
                $x    = (int) round($cursorX);
                $y    = self::PAD_Y + ($rowIdx * (self::NODE_H + self::V_GAP));

                if ($step->flowchart_position_saved) {
                    $x = max((int) $step->flowchart_x, 0);
                    $y = max((int) $step->flowchart_y, 0);
                }

                $m['x']              = $x;
                $m['y']              = $y;
                $m['position_saved'] = (bool) $step->flowchart_position_saved;
                $nodes[]             = $m;
                $cursorX            += $m['width'] + self::H_GAP;
            }
        }

        // Subprocedure nodes (right lane)
        $subList = array_values($subSources);
        usort($subList, function ($a, $b) use ($levels) {
            $aMin = $a['source_task_ids'] ? min(array_map(fn ($id) => $levels[$id] ?? 0, $a['source_task_ids'])) : 0;
            $bMin = $b['source_task_ids'] ? min(array_map(fn ($id) => $levels[$id] ?? 0, $b['source_task_ids'])) : 0;
            if ($aMin !== $bMin) return $aMin - $bMin;
            return min($a['source_task_ids']) - min($b['source_task_ids']);
        });

        $rightEdge = $nodes ? max(array_map(fn ($n) => $n['x'] + $n['width'], $nodes)) : ($innerW + self::PAD_X);
        $subX      = (int) ($rightEdge + self::SUB_LANE);
        $subY      = self::PAD_Y;

        $savedSubPositions = $template->flowchart_sub_positions ?? [];

        foreach ($subList as $sub) {
            $savedPos = $savedSubPositions[$sub['template_id']] ?? null;
            $nodes[] = [
                'id'             => $sub['id'],
                'label'          => $sub['label'],
                'type'           => 'subprocedure',
                'tag'            => 'SUB PROCEDURE',
                'badges'         => [],
                'width'          => self::SUB_W,
                'height'         => self::SUB_H,
                'x'              => $savedPos ? max((int) $savedPos['x'], 0) : $subX,
                'y'              => $savedPos ? max((int) $savedPos['y'], 0) : $subY,
                'position_saved' => $savedPos !== null,
            ];
            $subY += self::SUB_H + self::SUB_GAP;
        }

        // World bounding box
        $ww = $nodes ? max(array_map(fn ($n) => $n['x'] + $n['width'], $nodes)) + self::PAD_X  : 1200;
        $wh = $nodes ? max(array_map(fn ($n) => $n['y'] + $n['height'], $nodes)) + self::PAD_Y + 60 : 900;

        return [
            'template'    => ['id' => $template->id, 'name' => $template->name],
            'node_width'  => self::NODE_W,
            'node_height' => self::NODE_H,
            'nodes'       => $nodes,
            'edges'       => $edges,
            'world'       => ['width' => (int) max($ww, 900), 'height' => (int) max($wh, 500)],
        ];
    }
}
