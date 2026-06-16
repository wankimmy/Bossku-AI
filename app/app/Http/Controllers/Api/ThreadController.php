<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Thread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Threads group a sequence of runs that share conversational lineage.
 */
class ThreadController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Thread::query()->latest()->get()]);
    }

    public function show(string $id): JsonResponse
    {
        $thread = Thread::with('runs')->findOrFail($id);

        return response()->json([
            'thread' => $thread->makeHidden('runs'),
            'runs' => $thread->runs,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'assistant_id' => ['sometimes', 'nullable', 'string', 'exists:bossku_ai_assistants,id'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        return response()->json(Thread::query()->create($data), 201);
    }

    public function destroy(string $id): JsonResponse
    {
        Thread::findOrFail($id)->delete();

        return response()->json(['message' => 'Thread deleted.']);
    }
}
