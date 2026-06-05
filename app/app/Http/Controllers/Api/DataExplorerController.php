<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Data\DataExplorerService;
use Illuminate\Http\Request;

class DataExplorerController extends Controller
{
    public function __construct(
        protected DataExplorerService $explorer
    ) {}

    public function tables()
    {
        return response()->json($this->explorer->listTables());
    }

    public function index(Request $request, string $table)
    {
        try {
            $this->explorer->assertAllowedTable($table);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 25)));
        $search = $request->query('search');
        $sort = $request->query('sort');
        $sortDir = (string) $request->query('dir', 'desc');

        return response()->json($this->explorer->listRows(
            $table,
            $page,
            $perPage,
            is_string($search) ? $search : null,
            is_string($sort) ? $sort : null,
            $sortDir
        ));
    }

    public function show(string $table, string $id)
    {
        try {
            return response()->json($this->explorer->getRow($table, $id));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }
}
