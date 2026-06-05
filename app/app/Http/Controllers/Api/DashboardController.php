<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\AgentMessage;
use App\Models\BosskuAi\Memory;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\Skill;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = Cache::remember('dashboard.stats', 30, function () {
            return [
                'total_runs'   => Run::count(),
                'runs_today'   => Run::whereDate('created_at', today())->count(),
                'active_runs'  => Run::where('status', 'running')->count(),
                'skills_count' => Skill::count(),
                'memory_count' => Memory::count(),
            ];
        });

        $recentRuns = Run::query()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'status', 'prompt', 'created_at', 'estimated_cost', 'risk_level'])
            ->map(fn ($r) => [
                'id'             => $r->id,
                'status'         => $r->status,
                'prompt_excerpt' => mb_substr($r->prompt ?? '', 0, 120),
                'created_at'     => $r->created_at,
                'estimated_cost' => $r->estimated_cost,
                'risk_level'     => $r->risk_level,
            ]);

        $agentStatuses = AgentMessage::query()
            ->select('agent', DB::raw('max(created_at) as last_activity_at'))
            ->whereNotNull('agent')
            ->groupBy('agent')
            ->orderByDesc('last_activity_at')
            ->get()
            ->map(fn ($row) => [
                'agent'            => $row->agent,
                'last_activity_at' => $row->last_activity_at,
            ]);

        return response()->json([
            'stats'          => $stats,
            'recent_runs'    => $recentRuns,
            'agent_statuses' => $agentStatuses,
        ]);
    }
}
