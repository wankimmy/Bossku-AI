<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Providers\CliSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentHookController extends Controller
{
    public function __construct(
        private readonly CliSessionService $sessions,
    ) {}

    public function ingest(Request $request): JsonResponse
    {
        $allowedHosts = $this->allowedHookHosts();
        $clientHost = (string) ($request->ip() ?? '');
        if ($allowedHosts !== [] && ! $this->isAllowedHookHost($clientHost, $allowedHosts)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $token = (string) config('bossku.agent_hook_token', '');
        if ($token === '') {
            return response()->json(['message' => 'Agent hooks are disabled until BOSSKU_AGENT_HOOK_TOKEN is set.'], 503);
        }
        $provided = (string) $request->header('X-Bossku-Hook-Token', '');
        if (! hash_equals($token, $provided)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'session_id' => 'required|uuid',
            'event' => 'required|string|max:64',
            'payload' => 'nullable|array',
        ]);

        $session = $this->sessions->recordHookEvent(
            $validated['session_id'],
            $validated['event'],
            is_array($validated['payload'] ?? null) ? $validated['payload'] : [],
        );

        if ($session === null) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        return response()->json(['ok' => true, 'session' => $session]);
    }

    /**
     * @return list<string>
     */
    protected function allowedHookHosts(): array
    {
        $raw = (string) config('bossku.agent_hook_allowed_hosts', '127.0.0.1,localhost');
        $parts = array_map('trim', explode(',', $raw));

        return array_values(array_filter($parts, static fn (string $h) => $h !== ''));
    }

    /**
     * @param  list<string>  $allowed
     */
    protected function isAllowedHookHost(string $clientHost, array $allowed): bool
    {
        if ($clientHost === '') {
            return false;
        }

        foreach ($allowed as $host) {
            if (strcasecmp($clientHost, $host) === 0) {
                return true;
            }
        }

        return false;
    }
}
