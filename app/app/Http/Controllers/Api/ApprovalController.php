<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Approval;
use App\Services\Governance\ApprovalGateService;
use App\Services\Governance\ExecutorApprovalService;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalGateService $gates,
        private readonly ExecutorApprovalService $executorApprovals,
    ) {}
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
        $approval = Approval::findOrFail($id);

        return response()->json($this->executorApprovals->serializeApproval($approval));
    }

    public function approve(string $id, Request $request)
    {
        $approval = Approval::findOrFail($id);

        if ($approval->status !== 'pending') {
            return response()->json(['message' => 'Approval is not pending.'], 422);
        }

        $this->gates->decide($approval->id, 'approved', 'user', $request->input('note'));
        $approval = $approval->fresh() ?? $approval;

        try {
            $this->executorApprovals->applyApproved($approval);
        }
        catch (\Throwable $e) {
            $this->gates->decide($approval->id, 'rejected', 'system', 'Apply failed: '.$e->getMessage());

            return response()->json([
                'message' => 'Approved but apply failed: '.$e->getMessage(),
                'approval' => $this->executorApprovals->serializeApproval($approval->fresh() ?? $approval),
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
        $approval = Approval::findOrFail($id);

        if ($approval->status !== 'pending') {
            return response()->json(['message' => 'Approval is not pending.'], 422);
        }

        $this->gates->decide($approval->id, 'rejected', 'user', $request->input('note'));
        $approval = $approval->fresh() ?? $approval;

        return response()->json([
            'message' => 'Approval rejected.',
            'approval' => $this->executorApprovals->serializeApproval($approval),
            'run_has_pending' => $approval->run_id
                ? $this->executorApprovals->hasPendingForRun((string) $approval->run_id)
                : false,
        ]);
    }
}
