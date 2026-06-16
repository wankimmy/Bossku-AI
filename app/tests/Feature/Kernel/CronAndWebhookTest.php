<?php

namespace Tests\Feature\Kernel;

use App\Models\BosskuAi\Assistant;
use App\Models\BosskuAi\CronJob;
use App\Models\BosskuAi\Webhook;
use App\Services\Kernel\Platform\CronService;
use App\Services\Kernel\Platform\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CronAndWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function assistant(): Assistant
    {
        return Assistant::query()->create(['name' => 'A', 'slug' => 'a-'.uniqid(), 'graph' => 'default_pipeline']);
    }

    #[Test]
    public function cron_service_detects_due_jobs_for_the_current_minute(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-16 00:00:00'));
        $crons = new CronService;
        $assistant = $this->assistant();

        $midnight = CronJob::query()->create(['assistant_id' => $assistant->id, 'name' => 'midnight', 'cron_expression' => '0 0 * * *']);
        $noon = CronJob::query()->create(['assistant_id' => $assistant->id, 'name' => 'noon', 'cron_expression' => '0 12 * * *']);
        $disabled = CronJob::query()->create(['assistant_id' => $assistant->id, 'name' => 'off', 'cron_expression' => '* * * * *', 'enabled' => false]);

        $this->assertTrue($crons->isDue($midnight));
        $this->assertFalse($crons->isDue($noon));
        $this->assertFalse($crons->isDue($disabled));

        $due = $crons->due();
        $this->assertCount(1, $due);
        $this->assertSame('midnight', $due->first()->name);

        Carbon::setTestNow();
    }

    #[Test]
    public function cron_service_marks_ran_and_advances_next_run(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-16 00:00:00'));
        $crons = new CronService;
        $job = CronJob::query()->create(['assistant_id' => $this->assistant()->id, 'name' => 'daily', 'cron_expression' => '0 0 * * *']);

        $crons->markRan($job);

        $this->assertNotNull($job->last_run_at);
        $this->assertSame('2026-06-17 00:00:00', $job->next_run_at->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
    }

    #[Test]
    public function run_due_crons_command_processes_jobs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-16 00:00:00'));
        CronJob::query()->create(['assistant_id' => $this->assistant()->id, 'name' => 'midnight', 'cron_expression' => '0 0 * * *']);

        $this->artisan('bossku:run-due-crons')
            ->expectsOutputToContain('Processed 1 due cron job(s).')
            ->assertExitCode(0);

        Carbon::setTestNow();
    }

    #[Test]
    public function webhook_dispatcher_delivers_signed_payloads_to_subscribers(): void
    {
        Http::fake();

        Webhook::query()->create([
            'url' => 'https://example.test/hook',
            'events' => ['run.completed'],
            'secret' => 'topsecret',
        ]);
        Webhook::query()->create([
            'url' => 'https://other.test/hook',
            'events' => ['checkpoint.created'], // not subscribed to run.completed
        ]);
        Webhook::query()->create([
            'url' => 'https://disabled.test/hook',
            'events' => ['run.completed'],
            'enabled' => false,
        ]);

        $delivered = (new WebhookDispatcher)->dispatch('run.completed', ['run_id' => 'r1']);

        $this->assertSame(1, $delivered);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.test/hook'
                && $request['event'] === 'run.completed'
                && $request->hasHeader('X-Bossku-Signature');
        });
        Http::assertNotSent(fn ($request) => $request->url() === 'https://other.test/hook');
    }
}
