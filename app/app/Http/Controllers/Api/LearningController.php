<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\LearningEvent;
use App\Services\Learning\LearningEventProcessor;
use Illuminate\Http\Request;

class LearningController extends Controller
{
    public function __construct(
        protected LearningEventProcessor $processor,
    ) {}
    public function index(Request $request)
    {
        $query = LearningEvent::query()->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->paginate(30));
    }

    public function accept(string $id)
    {
        $event = LearningEvent::findOrFail($id);
        $event->update(['status' => 'accepted', 'reviewed_at' => now()]);

        $memory = $this->processor->processEvent($event->getKey(), 'manual');
        $event->refresh();

        return response()->json([
            'message' => 'Learning event accepted.',
            'event' => $event,
            'memory_id' => $memory?->id,
        ]);
    }

    public function reject(string $id)
    {
        $event = LearningEvent::findOrFail($id);
        $event->update(['status' => 'rejected', 'reviewed_at' => now()]);

        return response()->json(['message' => 'Learning event rejected.', 'event' => $event]);
    }
}
