<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\RunStep;
use Illuminate\Support\Facades\DB;

class AgentController extends Controller
{
    public function index()
    {
        $agents = RunStep::query()
            ->select(
                'type as role',
                DB::raw('max(created_at) as last_used_at'),
                DB::raw('count(*) as run_count'),
                DB::raw('avg(latency_ms) as avg_latency_ms'),
            )
            ->whereNotNull('type')
            ->groupBy('type')
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn ($row) => [
                'role'           => $row->role,
                'last_used_at'   => $row->last_used_at,
                'run_count'      => (int) $row->run_count,
                'avg_latency_ms' => $row->avg_latency_ms !== null ? round((float) $row->avg_latency_ms, 2) : null,
            ]);

        return response()->json($agents);
    }

    public function show(string $role)
    {
        $row = RunStep::query()
            ->select(
                'type as role',
                DB::raw('max(created_at) as last_used_at'),
                DB::raw('count(*) as run_count'),
                DB::raw('avg(latency_ms) as avg_latency_ms'),
                DB::raw('sum(latency_ms) as total_latency_ms'),
                DB::raw('avg(token_estimate) as avg_token_estimate'),
            )
            ->where('type', $role)
            ->groupBy('type')
            ->first();

        if (! $row) {
            return response()->json(['message' => 'Agent role not found.'], 404);
        }

        return response()->json([
            'role'              => $row->role,
            'last_used_at'      => $row->last_used_at,
            'run_count'         => (int) $row->run_count,
            'avg_latency_ms'    => $row->avg_latency_ms !== null ? round((float) $row->avg_latency_ms, 2) : null,
            'total_latency_ms'  => (int) $row->total_latency_ms,
            'avg_token_estimate'=> $row->avg_token_estimate !== null ? round((float) $row->avg_token_estimate, 2) : null,
        ]);
    }
}
