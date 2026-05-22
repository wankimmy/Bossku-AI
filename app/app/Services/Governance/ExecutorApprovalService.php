<?php

namespace App\Services\Governance;

use App\Models\BosskuAi\Approval;
use App\Services\Project\FileWriteApplier;
use App\Services\Project\ProjectCommandRunner;
use App\Services\Project\ProjectPathResolver;
use App\Support\StringCoercion;

/**
 * Proposes executor file/command changes for human approval and applies approved items.
 */
class ExecutorApprovalService
{
    public function __construct(
        private readonly ApprovalGateService $gates,
        private readonly RiskClassifier $riskClassifier,
        private readonly FileWriteApplier $fileWrites,
        private readonly ProjectPathResolver $paths,
        private readonly ProjectCommandRunner $commands,
    ) {}

    public function requireUserApproval(): bool
    {
        return (bool) config('bossku.require_user_approval_before_apply', true);
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @return array{execResult: array<string, mixed>, pending_approval_ids: list<string>}
     */
    public function proposeFileChanges(string $runId, array $execResult): array
    {
        $pending = [];
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

            $path = StringCoercion::toString($item['path'] ?? null);
            if ($path === '') {
                continue;
            }

            $changeType = StringCoercion::toString($item['change_type'] ?? null, 'modified');

            try {
                $resolved = $this->paths->resolve($path);
                $before = is_file($resolved['absolute']) ? (string) file_get_contents($resolved['absolute']) : '';
                $after = $changeType === 'deleted' ? '' : ($this->fileWrites->extractAfterContent($item, $path) ?? '');
                if ($changeType !== 'deleted' && $after === '') {
                    $enriched[] = $item;

                    continue;
                }

                $diff = $this->paths->unifiedDiff($resolved['relative'], $before, $after);
                $risk = $this->riskForFileChange($resolved['relative'], $changeType, $after);

                $approval = $this->gates->createApproval(
                    $runId,
                    null,
                    'file_write',
                    $this->describeFileChange($resolved['relative'], $changeType),
                    $risk,
                    [
                        'path' => $resolved['relative'],
                        'before' => $before,
                        'after' => $after,
                        'diff' => $diff,
                        'change_type' => $changeType,
                        'summary' => StringCoercion::toString($item['summary'] ?? null),
                        'why' => StringCoercion::toString($item['why'] ?? null),
                    ],
                );

                $pending[] = $approval->id;
                $enriched[] = array_merge($item, [
                    'path' => $resolved['relative'],
                    'before' => $before,
                    'after' => $after,
                    'diff' => $diff,
                    'change_type' => $changeType,
                    'approval_id' => $approval->id,
                    'approval_status' => 'pending',
                ]);
            }
            catch (\Throwable $e) {
                $enriched[] = array_merge($item, ['approval_error' => $e->getMessage()]);
            }
        }

        $execResult['files_changed'] = $enriched;
        $execResult['_approvals_pending'] = $pending;

        return ['execResult' => $execResult, 'pending_approval_ids' => $pending];
    }

    /**
     * @param  list<mixed>  $commandsRun
     * @return list<string>
     */
    public function proposeCommands(string $runId, array $commandsRun): array
    {
        $pending = [];
        foreach ($this->commands->normalizeCommandList($commandsRun) as $command) {
            $validation = $this->commands->validateCommand($command);
            if ($validation !== null) {
                continue;
            }

            $risk = $this->riskForCommand($command);
            $approval = $this->gates->createApproval(
                $runId,
                null,
                'terminal_command',
                'Run command: '.$command,
                $risk,
                [
                    'command' => $command,
                    'destructive' => $this->isDestructiveCommand($command),
                ],
            );
            $pending[] = $approval->id;
        }

        return $pending;
    }

    public function applyApproved(Approval $approval): void
    {
        if (! in_array($approval->operation_type, ['file_write', 'terminal_command'], true)) {
            return;
        }

        if ($approval->operation_type === 'file_write') {
            $this->fileWrites->applyApproval($approval);

            return;
        }

        if ($approval->operation_type === 'terminal_command') {
            /** @var array<string, mixed> $evidence */
            $evidence = is_array($approval->evidence) ? $approval->evidence : [];
            $command = StringCoercion::toString($evidence['command'] ?? null, '');
            if ($command === '') {
                throw new \InvalidArgumentException('Missing command in approval evidence.');
            }

            $outcome = $this->commands->runAllowedProjectCommands([$command]);
            $row = $outcome['executed'][0] ?? null;
            if (! is_array($row) || ($row['ok'] ?? false) !== true) {
                $err = is_array($row)
                    ? StringCoercion::toString($row['stderr'] ?? $row['reason'] ?? null, 'Command failed')
                    : 'Command failed';
                throw new \RuntimeException($err);
            }

            /** @var array<string, mixed> $evidence */
            $evidence = is_array($approval->evidence) ? $approval->evidence : [];
            $evidence['command_result'] = $row;
            $approval->evidence = $evidence;
            $approval->save();

            return;
        }

        throw new \InvalidArgumentException('Unsupported approval operation: '.$approval->operation_type);
    }

    /**
     * @return list<string>
     */
    public function pendingIdsForRun(string $runId): array
    {
        return Approval::query()
            ->where('run_id', $runId)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->pluck('id')
            ->all();
    }

    public function hasPendingForRun(string $runId): bool
    {
        return Approval::query()
            ->where('run_id', $runId)
            ->where('status', 'pending')
            ->exists();
    }

    /**
     * @return list<array{id: string, operation_type: string, description: string, risk_level: string, evidence: array<string, mixed>, status: string}>
     */
    public function pendingPayloadForRun(string $runId): array
    {
        return Approval::query()
            ->where('run_id', $runId)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Approval $a) => $this->serializeApproval($a))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeApproval(Approval $approval): array
    {
        /** @var array<string, mixed> $evidence */
        $evidence = is_array($approval->evidence) ? $approval->evidence : [];

        return [
            'id' => $approval->id,
            'run_id' => $approval->run_id,
            'operation_type' => $approval->operation_type,
            'description' => $approval->operation_description,
            'risk_level' => $approval->risk_level,
            'evidence' => $evidence,
            'status' => $approval->status,
            'decision_note' => $approval->decision_note,
            'created_at' => $approval->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return string
     */
    public function formatDecisionFeedback(string $runId): string
    {
        $decided = Approval::query()
            ->where('run_id', $runId)
            ->whereIn('status', ['approved', 'rejected'])
            ->where('decided_at', '>=', now()->subHours(2))
            ->orderBy('created_at')
            ->get();

        if ($decided->isEmpty()) {
            return '';
        }

        $lines = ['User decisions on proposed changes:'];
        foreach ($decided as $approval) {
            /** @var array<string, mixed> $evidence */
            $evidence = is_array($approval->evidence) ? $approval->evidence : [];
            $target = StringCoercion::toString(
                $evidence['path'] ?? $evidence['command'] ?? null,
                $approval->operation_description,
            );
            $note = trim((string) ($approval->decision_note ?? ''));
            $suffix = $note !== '' ? " — User note: {$note}" : '';
            $lines[] = '- '.strtoupper((string) $approval->status).': '.$target.$suffix;
        }

        return implode("\n", $lines);
    }

    protected function riskForFileChange(string $relative, string $changeType, string $after): string
    {
        if ($changeType === 'deleted') {
            return 'critical';
        }

        return $this->riskClassifier->classify($relative.' '.$after);
    }

    protected function riskForCommand(string $command): string
    {
        if ($this->isDestructiveCommand($command)) {
            return 'critical';
        }

        $lower = strtolower($command);

        return str_contains($lower, 'git restore') || str_contains($lower, 'git checkout')
            ? 'high'
            : 'medium';
    }

    protected function isDestructiveCommand(string $command): bool
    {
        $lower = strtolower(trim($command));

        return str_contains($lower, 'git restore')
            || str_contains($lower, 'git checkout')
            || str_contains($lower, 'delete');
    }

    protected function describeFileChange(string $relative, string $changeType): string
    {
        return match ($changeType) {
            'deleted' => 'Delete file: '.$relative,
            'created' => 'Create file: '.$relative,
            default => 'Modify file: '.$relative,
        };
    }
}
