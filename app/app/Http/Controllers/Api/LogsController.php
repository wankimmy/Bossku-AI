<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\LogEntry;
use Illuminate\Http\Request;

class LogsController extends Controller
{
    public function index(Request $request)
    {
        $query = LogEntry::query()->orderByDesc('created_at');

        if ($request->filled('level')) {
            $query->where('level', $request->query('level'));
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->query('channel'));
        }

        if ($request->filled('run_id')) {
            $query->where('run_id', $request->query('run_id'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where('message', 'like', '%'.$search.'%');
        }

        return response()->json($query->paginate(100));
    }
}
