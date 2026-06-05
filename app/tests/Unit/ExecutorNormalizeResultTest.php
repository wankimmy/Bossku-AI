<?php

namespace Tests\Unit;

use App\Services\Orchestrator\ExecutorService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class ExecutorNormalizeResultTest extends TestCase
{
    #[Test]
    public function normalize_result_does_not_throw_when_llm_returns_array_fields(): void
    {
        $service = app(ExecutorService::class);
        $method = new ReflectionMethod(ExecutorService::class, 'normalizeResult');
        $method->setAccessible(true);

        /** @var array<string, mixed> $result */
        $result = $method->invoke($service, [
            'status' => 'success',
            'files_read' => [['path' => 'routes/web.php', 'reason' => ['bootstrap']]],
            'files_changed' => [[
                'path' => 'routes/api.php',
                'summary' => ['Added health route'],
                'description' => ['Health check endpoint'],
                'why' => ['User asked for monitoring'],
            ]],
            'commands_run' => [['command' => ['php artisan test'], 'output_summary' => ['ok']]],
            'tests_result' => ['passed'],
            'patch_summary' => ['Updated API routes'],
            'handoff_message' => ['Ready for audit'],
        ], []);

        $this->assertSame('routes/api.php', $result['files_changed'][0]['path']);
        $this->assertStringContainsString('Added health route', $result['files_changed'][0]['summary']);
        $this->assertStringContainsString('User asked', $result['files_changed'][0]['why']);
        $this->assertStringContainsString('php artisan test', $result['commands_run'][0]['command']);
    }
}
