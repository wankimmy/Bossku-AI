<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Memory;
use App\Services\BosskuAi\MemoryService;
use Illuminate\Http\Request;

class MemoryApiController extends Controller
{
    public function __construct(
        protected MemoryService $memoryService
    ) {}

    public function index(Request $request)
    {
        $allowedColumns = ['updated_at', 'confidence', 'usage_count', 'created_at'];
        $sortColumn = in_array($request->query('sort_by'), $allowedColumns, true)
            ? $request->query('sort_by')
            : 'updated_at';
        $sortDir = $request->query('sort') === 'asc' ? 'asc' : 'desc';

        $q = Memory::query()->orderBy($sortColumn, $sortDir);

        if ($request->has('active')) {
            $q->where('is_active', $request->boolean('active'));
        }
        if ($t = $request->query('type')) {
            $q->where('type', $t);
        }
        if ($tag = $request->query('tag')) {
            $q->whereRaw('tags::text ILIKE ?', ['%'.$tag.'%']);
        }
        if ($s = $request->query('q')) {
            $q->where(function ($qq) use ($s) {
                $qq->where('content', 'ilike', '%'.$s.'%')
                    ->orWhere('human_summary', 'ilike', '%'.$s.'%');
            });
        }

        return $q->paginate(30);
    }

    public function search(Request $request)
    {
        $data = $request->validate([
            'query' => 'required|string|max:5000',
            'top_k' => 'sometimes|integer|min:1|max:25',
        ]);

        return $this->memoryService->search($data['query'], $data['top_k'] ?? null);
    }

    public function update(Request $request, string $id)
    {
        $data = $request->validate([
            'content' => 'sometimes|string',
            'human_summary' => 'sometimes|string|nullable',
            'type' => 'sometimes|string',
            'tags' => 'sometimes|array',
            'metadata' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);

        return $this->memoryService->updateMemory($id, $data);
    }

    public function destroy(string $id)
    {
        $this->memoryService->deleteMemory($id);

        return response()->json(['ok' => true]);
    }

    public function humanize(string $id)
    {
        $m = Memory::query()->findOrFail($id);

        return $this->memoryService->humanize($m);
    }
}
