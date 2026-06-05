<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Approval;
use App\Services\Governance\ApprovalGateService;
use App\Services\Governance\ExecutorApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalGateService $gates,
        private readonly ExecutorApprovalService $executorApprovals,
    ) {}

    private function findApproval(string $id): ?Approval
    {
        return Approval::query()->find($id);
    }

    /**
     * @return \Illuminate\Http\JsonResponse|null
     */
    private function approvalNotFoundResponse(string $id)
    {
        return response()->json([
            'message' => 'Approval not found. It may have already been decided or removed—refresh the approval queue and try again.',
            'approval_id' => $id,
        ], 404);
    }
    public function index(Request $request)
    {
        $query = Approval::query()->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->paginate(30));
    }

    public function show(string $id)
    {
        $approval = $this->findApproval($id);
        if ($approval === null) {
            return $this->approvalNotFoundResponse($id);
        }

        return response()->json($this->executorApprovals->serializeApproval($approval));
    }

    public function approve(string $id, Request $request)
    {
        $approval = $this->findApproval($id);
        if ($approval === null) {
            return $this->approvalNotFoundResponse($id);
        }

        if (in_array($approval->status, ['approved', 'auto_approved'], true)) {
            $approval = $approval->fresh() ?? $approval;

            return response()->json([
                'message' => 'Approval was already granted.',
                'already_decided' => true,
                'approval' => $this->executorApprovals->serializeApproval($approval),
                'run_has_pending' => $approval->run_id
                    ? $this->executorApprovals->hasPendingForRun((string) $approval->run_id)
                    : false,
            ]);
        }

        if ($approval->status === 'rejected') {
            return response()->json(['message' => 'Approval was rejected and cannot be approved.'], 422);
        }

        if ($approval->status !== 'pending') {
            return response()->json(['message' => 'Approval is not pending.'], 422);
        }

        $serialized = $this->executorApprovals->serializeApproval($approval);
        if (($serialized['review_blocked'] ?? false) === true) {
            return response()->json([
                'message' => (string) ($serialized['review_block_reason'] ?? 'This change cannot be applied safely.'),
            ], 422);
        }

        $this->gates->decide($approval->id, 'approved', 'user', $request->input('note'));
        $approval = $approval->fresh() ?? $approval;

        try {
            $this->executorApprovals->applyApproved($approval);
        }
        catch (\Throwable $e) {
            Log::error('bossku.approval.apply_failed', [
                'approval_id' => $approval->id,
                'run_id' => $approval->run_id,
                'operation_type' => $approval->operation_type,
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
            $this->gates->decide($approval->id, 'rejected', 'system', 'Apply failed: '.$e->getMessage());

            return response()->json([
                'message' => 'Approved but apply failed: '.$e->getMessage(),
                'approval' => $this->executorApprovals->serializeApproval($approval->fresh() ?? $approval),
                'run_has_pending' => $approval->run_id
                    ? $this->executorApprovals->hasPendingForRun((string) $approval->run_id)
                    : false,
            ], 500);
        }

        return response()->json([
            'message' => 'Approval granted and applied.',
            'approval' => $this->executorApprovals->serializeApproval($approval->fresh() ?? $approval),
            'run_has_pending' => $approval->run_id
                ? $this->executorApprovals->hasPendingForRun((string) $approval->run_id)
                : false,
        ]);
    }

    public function reject(string $id, Request $request)
    {
        $approval = $this->findApproval($id);
        if ($approval === null) {
            return $this->approvalNotFoundResponse($id);
        }

        if ($approval->status === 'rejected') {
            $approval = $approval->fresh() ?? $approval;

            return response()->json([
                'message' => 'Approval was already rejected.',
                'already_decided' => true,
                'approval' => $this->executorApprovals->serializeApproval($approval),
                'run_has_pending' => $approval->run_id
                    ? $this->executorApprovals->hasPendingForRun((string) $approval->run_id)
                    : false,
            ]);
        }

        if (in_array($approval->status, ['approved', 'auto_approved'], true)) {
            return response()->json(['message' => 'Approval was granted and cannot be rejected.'], 422);
        }

        if ($approval->status !== 'pending') {
            return response()->json(['message' => 'Approval is not pending.'], 422);
        }

        $note = trim((string) $request->input('note', ''));
        $this->gates->decide($approval->id, 'rejected', 'user', $note !== '' ? $note : null);
        $approval = $approval->fresh() ?? $approval;
        $this->executorApprovals->markApprovalRequestChanges($approval, $note !== '');
        $revert = $this->executorApprovals->revertRejectedFileWrite($approval);

        return response()->json([
            'message' => 'Approval rejected.',
            'approval' => $this->executorApprovals->serializeApproval($approval),
            'file_reverted' => $revert['reverted'],
            'revert_detail' => $revert['message'],
            'run_has_pending' => $approval->run_id
                ? $this->executorApprovals->hasPendingForRun((string) $approval->run_id)
                : false,
        ]);
    }
}
