<?php

namespace Tests\Unit;

use App\Services\Orchestrator\AuditReportFormatter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditReportFormatterTest extends TestCase
{
    #[Test]
    public function it_groups_findings_by_dimension(): void
    {
        $lines = app(AuditReportFormatter::class)->formatDimensionSections([
            'findings' => [
                ['category' => 'functionality', 'severity' => 'high', 'title' => 'Broken checkout'],
                ['category' => 'performance', 'severity' => 'medium', 'title' => 'N+1 on orders'],
            ],
        ], null);

        $text = implode("\n", $lines);
        $this->assertStringContainsString('## Audit by dimension', $text);
        $this->assertStringContainsString('### Functionality', $text);
        $this->assertStringContainsString('Broken checkout', $text);
        $this->assertStringContainsString('### Performance', $text);
        $this->assertStringContainsString('N+1 on orders', $text);
    }
}
