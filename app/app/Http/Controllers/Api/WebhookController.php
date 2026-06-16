<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Webhook;
use App\Services\Kernel\Platform\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Outbound webhook subscriptions for kernel lifecycle events.
 */
class WebhookController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Webhook::query()->latest()->get(),
            'available_events' => WebhookDispatcher::EVENTS,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(WebhookDispatcher::EVENTS)],
            'secret' => ['sometimes', 'nullable', 'string'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $webhook = Webhook::query()->create($data);

        return response()->json($webhook, 201);
    }

    public function update(string $id, Request $request): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);

        $data = $request->validate([
            'url' => ['sometimes', 'url'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(WebhookDispatcher::EVENTS)],
            'secret' => ['sometimes', 'nullable', 'string'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $webhook->update($data);

        return response()->json($webhook);
    }

    public function destroy(string $id): JsonResponse
    {
        Webhook::findOrFail($id)->delete();

        return response()->json(['message' => 'Webhook deleted.']);
    }
}
