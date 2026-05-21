<?php

namespace Tests\Unit;

use App\Services\Orchestrator\ExecutorEvidenceSupport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecutorEvidenceSupportTest extends TestCase
{
    #[Test]
    public function count_files_read_only_includes_found_true(): void
    {
        $execResult = [
            'files_read' => [
                ['path' => 'routes/web.php', 'found' => true],
                ['path' => 'FiuuPaymentController.php', 'found' => false],
                ['path' => '', 'found' => true],
            ],
        ];

        $this->assertSame(1, ExecutorEvidenceSupport::countFilesRead($execResult));
        $this->assertSame(1, ExecutorEvidenceSupport::countFilesReadFailed($execResult));
    }
}
