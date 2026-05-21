<?php

namespace App\Services\Governance;

use App\Models\BosskuAi\Approval;
use App\Services\Project\FileWriteApplier;
use Illuminate\Support\Carbon;

class ApprovalGateService
{
    private const ALWAYS_GATE = [
        'external_http', 'env_mod', 'deployment', 'secret_rotation', 'file_write',
    ];

    public function __construct(
        private readonly RiskClassifier $classifier,
        private readonly FileWriteApplier $fileWrites,
    ) {}

    public function autoApplyFileWritesEnabled(): bool
    {
        return (bool) config('bossku.auto_apply_file_writes', true);
    }

    public function autoApproveAndApply(string $approvalId): Approval
    {
        $approval = $this->decide($approvalId, 'auto_approved', 'orchestrator', 'Auto-approved (BOSSKU_AUTO_APPLY_FILE_WRITES)');
        $this->fileWrites->applyApproval($approval->fresh() ?? $approval);

        return $approval->fresh() ?? $approval;
    }

    public function requiresApproval(
        string $operationType,
        string $description,
        string $riskLevel,
    ): bool {
        if (in_array($operationType, self::ALWAYS_GATE, true)) {
            return true;
        }

        if ($operationType === 'terminal_command') {
            return in_array($riskLevel, ['high', 'critical'], true);
        }

        if ($operationType === 'high_cost') {
            return in_array($riskLevel, ['high', 'critical'], true);
        }

        return false;
    }

    public function createApproval(
        string $runId,
        ?string $runStepId,
        string $operationType,
        string $description,
        string $riskLevel,
        array $evidence = [],
    ): Approval {
        return Approval::create([
            'run_id'                => $runId,
            'run_step_id'           => $runStepId,
            'operation_type'        => $operationType,
            'operation_description' => $description,
            'risk_level'            => $riskLevel,
            'evidence'              => $evidence,
            'status'                => 'pending',
        ]);
    }

    public function decide(
        string $approvalId,
        string $decision,
        string $decidedBy,
        ?string $note = null,
    ): Approval {
        $approval = Approval::findOrFail($approvalId);

        $approval->status        = $decision;
        $approval->decided_by    = $decidedBy;
        $approval->decided_at    = Carbon::now();
        $approval->decision_note = $note;
        $approval->save();

        return $approval;
    }

    public function isPendingFor(string $runId): bool
    {
        return Approval::where('run_id', $runId)
            ->where('status', 'pending')
            ->exists();
    }
}
