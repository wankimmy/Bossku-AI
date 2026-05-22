<?php

namespace Tests\Unit;

use App\Services\Orchestrator\ExecutorEvidenceSupport;
use App\Services\Orchestrator\SecurityAuditorService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityAuditorPreflightTest extends TestCase
{
    #[Test]
    public function executor_payload_includes_read_previews_from_preflight(): void
    {
        $preflight = [
            [
                'path' => 'app/Models/User.php',
                'found' => true,
                'preview' => '<?php namespace App\\Models; class User {}',
                'reason' => 'security target',
            ],
        ];

        $payload = ExecutorEvidenceSupport::executorPayloadForAudit(
            [
                'status' => 'success',
                'files_read' => [['path' => 'app/Models/User.php', 'found' => true]],
            ],
            $preflight,
            null,
            10,
        );

        $this->assertCount(1, $payload['read_previews']);
        $this->assertStringContainsString('User', $payload['read_previews'][0]['preview']);
    }

    #[Test]
    public function security_auditor_returns_deterministic_when_paths_without_previews(): void
    {
        $service = app(SecurityAuditorService::class);
        $result = $service->audit(
            'audit security',
            ['needs_security_auditor' => true],
            ['target_file_list' => []],
            [
                'status' => 'success',
                'files_read' => [['path' => 'app/Foo.php', 'found' => true]],
                'handoff_message' => 'test',
            ],
            null,
            [],
        );

        $this->assertTrue($result['_deterministic'] ?? false);
        $this->assertStringContainsString('readable content', strtolower((string) ($result['summary'] ?? '')));
    }
}
