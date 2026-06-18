<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\RunStreamEvent;
use Illuminate\Http\JsonResponse;

class StaffCouncilController extends Controller
{
    public function index(): JsonResponse
    {
        $events = RunStreamEvent::query()
            ->with('run:id,status,created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->filter(function (RunStreamEvent $event): bool {
                $type = (string) ($event->payload['type'] ?? '');

                return in_array($type, ['staff_council_started', 'staff_council_done', 'staff_council_skipped'], true);
            })
            ->values()
            ->map(fn (RunStreamEvent $event) => [
                'id' => $event->id,
                'run_id' => $event->run_id,
                'seq' => $event->seq,
                'type' => $event->payload['type'] ?? null,
                'summary' => $event->payload['summary'] ?? null,
                'payload' => $event->payload,
                'run' => $event->run,
                'created_at' => $event->created_at?->toISOString(),
            ]);

        return response()->json(['data' => $events]);
    }
}
