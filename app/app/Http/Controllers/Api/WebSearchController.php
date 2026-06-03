<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BosskuAi\KnowledgeImportService;
use App\Services\BosskuAi\WebSearchService;
use Illuminate\Http\Request;

class WebSearchController extends Controller
{
    public function __construct(
        protected WebSearchService $search,
    ) {}

    public function __invoke(Request $request, KnowledgeImportService $import)
    {
        $data = $request->validate([
            'query' => 'required|string|max:500',
            'limit' => 'sometimes|integer|min:1|max:25',
            'learn' => 'sometimes|integer|min:0|max:5',
        ]);

        $results = $this->search->search($data['query'], $data['limit'] ?? 8);

        $learned = [];
        $learnCount = (int) ($data['learn'] ?? 0);
        foreach (array_slice($results, 0, $learnCount) as $result) {
            try {
                $learned[] = $import->learnUrl($result['url']) + ['ok' => true];
            } catch (\Throwable $e) {
                $learned[] = [
                    'url' => $result['url'],
                    'ok' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'query' => $data['query'],
            'results' => $results,
            'learned' => $learned,
        ]);
    }
}
