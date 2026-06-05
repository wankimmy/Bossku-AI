<?php

namespace App\Services\Orchestrator;

use App\Models\BosskuAi\FileChange;
use App\Models\BosskuAi\Run;
use App\Services\BosskuAi\LlmGateway;
use App\Services\BosskuAi\RuntimeSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupervisorMergeCoordinator
{
    public function __construct(
        private readonly LlmGateway $llm,
        private readonly RuntimeSettings $settings,
    ) {}

    /**
     * @param  Collection<int, Run>  $children
     * @return array{final_output: string, status: string, merge_report: array<string, mixed>}
     */
    public function synthesize(Run $parent, Collection $children): array
    {
        $report = $this->buildMergeReport($parent, $children);
        $structured = $this->formatStructuredOutput($parent, $report);
        $finalOutput = $structured;

        if ((bool) config('bossku.supervisor_llm_synthesis', false)) {
            try {
                $finalOutput = $this->llmSynthesize($parent, $report, $structured);
            } catch (\Throwable $e) {
                Log::warning('bossku.supervisor.llm_synthesis_failed', [
                    'parent_run_id' => $parent->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $allOk = $children->every(fn (Run $c) => $c->status === 'completed');
        $anyFailed = $children->contains(fn (Run $c) => $c->status === 'failed');

        return [
            'final_output' => $finalOutput,
            'status' => $allOk ? 'completed' : ($anyFailed ? 'partial' : 'partial'),
            'merge_report' => $report,
        ];
    }

    /**
     * @param  Collection<int, Run>  $children
     * @return array<string, mixed>
     */
    protected function buildMergeReport(Run $parent, Collection $children): array
    {
        $childReports = $children->map(function (Run $child) {
            $workspace = $child->workspace;
            $files = FileChange::query()
                ->where('run_id', $child->getKey())
                ->orderBy('file_path')
                ->get()
                ->map(fn (FileChange $fc) => [
                    'path' => $fc->file_path,
                    'type' => $fc->change_type,
                ])
                ->values()
                ->all();

            $meta = is_array($child->metadata) ? $child->metadata : [];

            return [
                'run_id' => $child->getKey(),
                'slot' => $child->supervisor_slot,
                'status' => $child->status,
                'prompt' => Str::limit((string) $child->prompt, 300),
                'branch_name' => $workspace?->branch_name,
                'worktree_path' => $workspace?->worktree_path,
                'audit_score' => $child->audit_score,
                'risk_level' => $child->risk_level,
                'files_changed' => $files,
                'files_changed_count' => count($files),
                'summary' => Str::limit((string) ($child->final_output ?? ''), 1500),
                'plan_goal' => is_array($meta['plan'] ?? null) ? ($meta['plan']['goal'] ?? null) : null,
            ];
        })->values()->all();

        $totalFiles = array_sum(array_column($childReports, 'files_changed_count'));
        $conflicts = $this->detectCrossBranchConflicts($childReports);

        return [
            'parent_run_id' => $parent->getKey(),
            'parent_prompt' => Str::limit((string) $parent->prompt, 500),
            'children_total' => $children->count(),
            'children_completed' => $children->where('status', 'completed')->count(),
            'children_failed' => $children->where('status', 'failed')->count(),
            'total_files_changed' => $totalFiles,
            'cross_branch_conflicts' => $conflicts,
            'children' => $childReports,
            'merged_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $childReports
     * @return list<array{path: string, slots: list<int|string>}>
     */
    protected function detectCrossBranchConflicts(array $childReports): array
    {
        $byPath = [];
        foreach ($childReports as $child) {
            $slot = $child['slot'] ?? '?';
            foreach ($child['files_changed'] ?? [] as $file) {
                if (! is_array($file)) {
                    continue;
                }
                $path = (string) ($file['path'] ?? '');
                if ($path === '') {
                    continue;
                }
                $byPath[$path] ??= [];
                $byPath[$path][] = $slot;
            }
        }

        $conflicts = [];
        foreach ($byPath as $path => $slots) {
            $unique = array_values(array_unique($slots));
            if (count($unique) > 1) {
                $conflicts[] = ['path' => $path, 'slots' => $unique];
            }
        }

        return $conflicts;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function formatStructuredOutput(Run $parent, array $report): string
    {
        $lines = [
            '# Supervisor merge report',
            '',
            '**Parent task:** '.(string) ($report['parent_prompt'] ?? $parent->prompt),
            '',
            sprintf(
                '**Children:** %d total · %d completed · %d failed · %d file change(s) across branches',
                (int) ($report['children_total'] ?? 0),
                (int) ($report['children_completed'] ?? 0),
                (int) ($report['children_failed'] ?? 0),
                (int) ($report['total_files_changed'] ?? 0),
            ),
            '',
        ];

        $conflicts = is_array($report['cross_branch_conflicts'] ?? null) ? $report['cross_branch_conflicts'] : [];
        if ($conflicts !== []) {
            $lines[] = '**Cross-branch file overlaps (manual merge may be required):**';
            foreach ($conflicts as $conflict) {
                if (! is_array($conflict)) {
                    continue;
                }
                $slots = is_array($conflict['slots'] ?? null) ? implode(', ', $conflict['slots']) : '?';
                $lines[] = '- `'.(string) ($conflict['path'] ?? '').'` — child slots: '.$slots;
            }
            $lines[] = '';
        }

        foreach ($report['children'] ?? [] as $child) {
            if (! is_array($child)) {
                continue;
            }
            $lines[] = '## Child slot '.(string) ($child['slot'] ?? '?').' — '.(string) ($child['status'] ?? 'unknown');
            if (! empty($child['branch_name'])) {
                $lines[] = '- **Branch:** `'.$child['branch_name'].'`';
            }
            $lines[] = '- **Run:** `'.(string) ($child['run_id'] ?? '').'`';
            $lines[] = '- **Files changed:** '.(int) ($child['files_changed_count'] ?? 0);
            if (! empty($child['files_changed']) && is_array($child['files_changed'])) {
                $paths = array_slice(array_map(fn ($f) => is_array($f) ? (string) ($f['path'] ?? '') : '', $child['files_changed']), 0, 12);
                $paths = array_values(array_filter($paths));
                if ($paths !== []) {
                    $lines[] = '- **Paths:** `'.implode('`, `', $paths).'`';
                }
            }
            $lines[] = '';
            $lines[] = (string) ($child['summary'] ?? '(no output)');
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function llmSynthesize(Run $parent, array $report, string $structuredFallback): string
    {
        $model = $this->settings->orchestratorModelForRouting();
        $payload = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: $structuredFallback;
        $out = $this->llm->chat($model, [
            [
                'role' => 'system',
                'content' => 'You merge parallel coding-agent child run results into one concise supervisor brief for the human operator. Include: overall status, per-child outcomes, conflicting edits if any, and recommended next steps (review branches, run tests, open PRs). Use markdown.',
            ],
            [
                'role' => 'user',
                'content' => "Parent prompt:\n".(string) $parent->prompt."\n\nChild merge JSON:\n".$payload,
            ],
        ], 0.3);

        $text = trim((string) ($out['text'] ?? ''));
        if ($text === '') {
            return $structuredFallback;
        }

        return "# Supervisor synthesis\n\n".$text."\n\n---\n\n".$structuredFallback;
    }
}
