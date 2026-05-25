<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BosskuAi\KnowledgeCaptureService;
use Illuminate\Http\Request;

class KnowledgeCaptureController extends Controller
{
    public function urls(Request $request, KnowledgeCaptureService $service)
    {
        $data = $request->validate([
            'urls' => 'required|array|min:1|max:25',
            'urls.*' => 'required|string|max:2048',
            'tags' => 'sometimes|array|max:20',
            'tags.*' => 'string|max:40',
            'note' => 'sometimes|string|nullable|max:1000',
        ]);

        return response()->json($service->importUrls(
            $data['urls'],
            $data['tags'] ?? [],
            $data['note'] ?? null
        ));
    }

    public function importMemory(Request $request, KnowledgeCaptureService $service)
    {
        $data = $request->validate([
            'source' => 'required|string|in:codex,claude',
        ]);

        return response()->json($service->importLocalMemory($data['source']));
    }

    public function recent(Request $request, KnowledgeCaptureService $service)
    {
        $limit = max(1, min(100, (int) $request->query('limit', 30)));

        return response()->json($service->recent($limit)['data']);
    }
}
