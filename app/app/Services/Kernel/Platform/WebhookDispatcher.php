<?php

namespace App\Services\Kernel\Platform;

use App\Models\BosskuAi\Webhook;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fans a kernel lifecycle event out to all subscribed, enabled webhooks. Signs
 * the body with HMAC-SHA256 when the webhook has a secret. Failures are logged,
 * not fatal — a bad endpoint never breaks a run.
 */
final class WebhookDispatcher
{
    public const EVENTS = [
        'run.completed',
        'run.interrupted',
        'run.failed',
        'checkpoint.created',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return int  number of webhooks delivered to
     */
    public function dispatch(string $event, array $data): int
    {
        $hooks = Webhook::query()
            ->where('enabled', true)
            ->get()
            ->filter(fn (Webhook $hook): bool => in_array($event, (array) $hook->events, true));

        $delivered = 0;
        foreach ($hooks as $hook) {
            $body = [
                'event' => $event,
                'data' => $data,
                'timestamp' => Carbon::now()->toIso8601String(),
            ];

            try {
                $request = Http::asJson()->timeout(5);
                if (! empty($hook->secret)) {
                    $signature = hash_hmac('sha256', (string) json_encode($body), (string) $hook->secret);
                    $request = $request->withHeaders(['X-Bossku-Signature' => $signature]);
                }
                $request->post($hook->url, $body);
                $delivered++;
            } catch (\Throwable $e) {
                Log::warning('Webhook delivery failed', ['webhook_id' => $hook->id, 'event' => $event, 'error' => $e->getMessage()]);
            }
        }

        return $delivered;
    }
}
