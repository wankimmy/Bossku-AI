<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\UsageEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsageController extends Controller
{
    public function index(Request $request)
    {
        $query = UsageEvent::query()->orderByDesc('created_at');

        if ($request->filled('run_id')) {
            $query->where('run_id', $request->query('run_id'));
        }

        if ($request->filled('provider')) {
            $query->where('provider', $request->query('provider'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        return response()->json($query->paginate(50));
    }

    public function summary(Request $request)
    {
        $query = UsageEvent::query();

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        $totals = (clone $query)->select(
            DB::raw('sum(input_tokens) as total_input_tokens'),
            DB::raw('sum(output_tokens) as total_output_tokens'),
            DB::raw('sum(cost_usd) as total_cost_usd'),
        )->first();

        $byProviderModel = (clone $query)
            ->select(
                'provider',
                'model',
                DB::raw('sum(input_tokens) as input_tokens'),
                DB::raw('sum(output_tokens) as output_tokens'),
                DB::raw('sum(cost_usd) as cost_usd'),
                DB::raw('count(*) as call_count'),
            )
            ->groupBy('provider', 'model')
            ->orderByDesc('cost_usd')
            ->get();

        return response()->json([
            'total_input_tokens'  => (int) ($totals->total_input_tokens ?? 0),
            'total_output_tokens' => (int) ($totals->total_output_tokens ?? 0),
            'total_cost_usd'      => $totals->total_cost_usd !== null ? round((float) $totals->total_cost_usd, 8) : 0,
            'breakdown'           => $byProviderModel,
        ]);
    }
}
