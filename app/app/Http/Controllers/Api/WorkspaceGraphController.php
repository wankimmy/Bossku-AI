<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Graph\WorkspaceGraphService;

class WorkspaceGraphController extends Controller
{
    public function __construct(
        private readonly WorkspaceGraphService $graph
    ) {}

    public function index()
    {
        return response()->json($this->graph->build());
    }
}
