<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BosskuAi\KnowledgeImportService;
use Illuminate\Http\Request;

class KnowledgeImportApiController extends Controller
{
    public function __invoke(Request $request, KnowledgeImportService $service)
    {
        abort_unless(app()->environment('local'), 403, 'Import is only allowed in local environment.');

        $stats = $service->import($request->boolean('fresh'));

        return response()->json($stats);
    }
}
