<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\FeedbackItem;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $query = FeedbackItem::query()->orderByDesc('created_at');

        if ($request->filled('target_type')) {
            $query->where('target_type', $request->query('target_type'));
        }

        if ($request->filled('signal')) {
            $query->where('signal', $request->query('signal'));
        }

        if ($request->has('processed')) {
            $query->where('processed', filter_var($request->query('processed'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($query->paginate(30));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'target_type' => 'required|string|max:100',
            'target_id'   => 'required|string|max:255',
            'signal'      => 'required|string|max:100',
            'rating'      => 'nullable|numeric',
            'comment'     => 'nullable|string',
        ]);

        $item = FeedbackItem::create($data);

        return response()->json($item, 201);
    }

    public function summary(string $targetType, string $targetId)
    {
        /** @var \App\Services\Feedback\FeedbackService $service */
        $service = app(\App\Services\Feedback\FeedbackService::class);

        return response()->json($service->summary($targetType, $targetId));
    }
}
