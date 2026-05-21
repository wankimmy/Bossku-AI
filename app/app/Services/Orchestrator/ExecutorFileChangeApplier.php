<?php

namespace App\Services\Orchestrator;

use App\Services\Governance\ApprovalGateService;
use App\Services\Governance\RiskClassifier;
use App\Services\Project\FileWriteApplier;
use App\Services\Project\ProjectPathResolver;
use Illuminate\Support\Facades\Log;

class ExecutorFileChangeApplier
{
    public function __construct(
        private readonly ApprovalGateService $approvals,
        private readonly RiskClassifier $riskClassifier,
        private readonly FileWriteApplier $fileWrites,
        private readonly ProjectPathResolver $paths,
    ) {}

    public function enabled(): bool
    {
        return $this->approvals->autoApplyFileWritesEnabled();
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @return array{applied: list<string>, skipped: list<string>, errors: list<string>, execResult: array<string, mixed>}
     */
    public function applyFromExecutorResult(string $runId, array $execResult): array
    {
        if (! $this->enabled()) {
            return ['applied' => [], 'skipped' => [], 'errors' => [], 'execResult' => $execResult];
        }

        $applied = [];
        $skipped = [];
        $errors = [];

        $files = is_array($execResult['files_changed'] ?? null) ? $execResult['files_changed'] : [];
        $enriched = [];

        foreach ($files as $item) {
            if (is_string($item)) {
                $item = ['path' => $item, 'change_type' => 'modified'];
            }
            if (! is_array($item)) {
                $enriched[] = $item;

                continue;
            }

            $path = (string) ($item['path'] ?? '');
            if ($path === '') {
                continue;
            }

            $changeType = (string) ($item['change_type'] ?? 'modified');

            try {
                if ($changeType === 'deleted') {
                    $patch = $this->applyDeleted($runId, $path, $item);
                    $enriched[] = array_merge($item, $patch);
                    $applied[] = $path;

                    continue;
                }

                $after = $this->fileWrites->extractAfterContent($item, $path);
                if ($after === null) {
                    $skipped[] = $path.' (no after/new_contents/diff)';
                    $enriched[] = $item;

                    continue;
                }

                $patch = $this->applyWrite($runId, $path, $after, $changeType, $item);
                $enriched[] = array_merge($item, $patch);
                $applied[] = $path;
            }
            catch (\Throwable $e) {
                $errors[] = $path.': '.$e->getMessage();
                $enriched[] = $item;
                Log::warning('Executor file auto-apply failed', [
                    'run_id' => $runId,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $execResult['files_changed'] = $enriched;

        return ['applied' => $applied, 'skipped' => $skipped, 'errors' => $errors, 'execResult' => $execResult];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    /**
     * @param  array<string, mixed>  $item
     * @return array{path: string, before: string, after: string, diff: string, change_type: string}
     */
    protected function applyWrite(
        string $runId,
        string $path,
        string $after,
        string $changeType,
        array $item,
    ): array {
        $resolved = $this->paths->resolve($path);
        $before = is_file($resolved['absolute']) ? (string) file_get_contents($resolved['absolute']) : '';
        $diff = $this->paths->unifiedDiff($resolved['relative'], $before, $after);
        $risk = $this->riskClassifier->classify($resolved['relative'].' '.$after);

        $approval = $this->approvals->createApproval(
            $runId,
            null,
            'file_write',
            'Auto-applied executor change: '.$resolved['relative'],
            $risk,
            [
                'path' => $resolved['relative'],
                'before' => $before,
                'after' => $after,
                'diff' => $diff,
                'change_type' => $changeType,
                'summary' => (string) ($item['summary'] ?? ''),
            ],
        );

        $this->approvals->autoApproveAndApply($approval->id);

        return [
            'path' => $resolved['relative'],
            'before' => $before,
            'after' => $after,
            'diff' => $diff,
            'change_type' => $changeType,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    /**
     * @param  array<string, mixed>  $item
     * @return array{path: string, before: string, after: string, diff: string, change_type: string}
     */
    protected function applyDeleted(string $runId, string $path, array $item): array
    {
        $resolved = $this->paths->resolve($path);
        $before = is_file($resolved['absolute']) ? (string) file_get_contents($resolved['absolute']) : '';
        $diff = $this->paths->unifiedDiff($resolved['relative'], $before, '');
        $risk = $this->riskClassifier->classify($resolved['relative'].' delete');

        $approval = $this->approvals->createApproval(
            $runId,
            null,
            'file_write',
            'Auto-applied executor delete: '.$resolved['relative'],
            $risk,
            [
                'path' => $resolved['relative'],
                'before' => $before,
                'after' => '',
                'diff' => $diff,
                'change_type' => 'deleted',
                'summary' => (string) ($item['summary'] ?? ''),
            ],
        );

        $this->approvals->autoApproveAndApply($approval->id);

        return [
            'path' => $resolved['relative'],
            'before' => $before,
            'after' => '',
            'diff' => $diff,
            'change_type' => 'deleted',
        ];
    }
}
