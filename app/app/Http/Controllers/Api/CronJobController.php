<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\CronJob;
use App\Services\Kernel\Platform\CronService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Scheduled assistant runs (crons).
 */
class CronJobController extends Controller
{
    public function __construct(private readonly CronService $crons) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => CronJob::query()->with('assistant')->latest()->get()]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(CronJob::with('assistant')->findOrFail($id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'assistant_id' => ['required', 'string', 'exists:bossku_ai_assistants,id'],
            'name' => ['required', 'string', 'max:255'],
            'cron_expression' => ['required', 'string', function ($attr, $value, $fail) {
                if (! $this->crons->isValidExpression($value)) {
                    $fail("The {$attr} is not a valid cron expression.");
                }
            }],
            'prompt' => ['sometimes', 'nullable', 'string'],
            'payload' => ['sometimes', 'array'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $job = CronJob::query()->create($data);
        $job->update(['next_run_at' => $this->crons->nextRun($job)]);

        return response()->json($job, 201);
    }

    public function update(string $id, Request $request): JsonResponse
    {
        $job = CronJob::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'cron_expression' => ['sometimes', 'string', function ($attr, $value, $fail) {
                if (! $this->crons->isValidExpression($value)) {
                    $fail("The {$attr} is not a valid cron expression.");
                }
            }],
            'prompt' => ['sometimes', 'nullable', 'string'],
            'payload' => ['sometimes', 'array'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $job->update($data);
        if (isset($data['cron_expression'])) {
            $job->update(['next_run_at' => $this->crons->nextRun($job)]);
        }

        return response()->json($job);
    }

    public function destroy(string $id): JsonResponse
    {
        CronJob::findOrFail($id)->delete();

        return response()->json(['message' => 'Cron job deleted.']);
    }
}
