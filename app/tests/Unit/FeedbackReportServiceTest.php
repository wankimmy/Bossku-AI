<?php

namespace Tests\Unit;

use App\Models\BosskuAi\FeedbackReport;
use App\Models\BosskuAi\Run;
use App\Services\Learning\FeedbackReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeedbackReportServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_deduped_feedback_reports(): void
    {
        $run = Run::query()->create(['prompt' => 'x', 'status' => 'completed']);
        $service = app(FeedbackReportService::class);

        $a = $service->record('bug_report', 'Missing null check', [], $run);
        $b = $service->record('bug_report', 'Missing null check', [], $run);

        $this->assertSame($a->getKey(), $b->getKey());
        $this->assertSame(1, FeedbackReport::query()->count());
    }
}
