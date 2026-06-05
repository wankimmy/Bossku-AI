<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\FeedbackReport;
use App\Models\BosskuAi\Run;
use App\Services\Learning\FeedbackReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackReportController extends Controller
{
    public function __construct(
        private readonly FeedbackReportService $reports,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = FeedbackReport::query()->orderByDesc('created_at');
        if ($request->filled('run_id')) {
            $query->where('run_id', $request->string('run_id'));
        }

        return response()->json($query->limit(100)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_type' => 'required|string|in:bug_report,improvement_suggestion,ci_failure,review_comment',
            'summary' => 'required|string|max:10000',
            'run_id' => 'nullable|uuid',
            'evidence' => 'nullable|array',
            'confidence' => 'nullable|numeric|min:0|max:1',
        ]);

        $run = isset($validated['run_id']) ? Run::query()->find($validated['run_id']) : null;
        $report = $this->reports->record(
            $validated['report_type'],
            $validated['summary'],
            is_array($validated['evidence'] ?? null) ? $validated['evidence'] : [],
            $run,
            (float) ($validated['confidence'] ?? 0.75),
        );

        return response()->json($report, 201);
    }

    public function verify(Request $request, string $id): JsonResponse
    {
        $report = FeedbackReport::query()->find($id);
        if ($report === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'passed' => 'required|boolean',
            'output' => 'nullable|string|max:50000',
        ]);

        $report = $this->reports->verify($report, (bool) $validated['passed'], $validated['output'] ?? null);

        return response()->json($report);
    }
}
