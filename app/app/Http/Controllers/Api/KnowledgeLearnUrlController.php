<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BosskuAi\KnowledgeImportService;
use Illuminate\Http\Request;

class KnowledgeLearnUrlController extends Controller
{
    public function __invoke(Request $request, KnowledgeImportService $service)
    {
        $request->validate(['url' => 'required|url|max:2048']);

        try {
            $result = $service->learnUrl($request->input('url'));
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
