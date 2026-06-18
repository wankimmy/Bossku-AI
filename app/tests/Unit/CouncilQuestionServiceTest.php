<?php

namespace Tests\Unit;

use App\Services\Council\CouncilQuestionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CouncilQuestionServiceTest extends TestCase
{
    #[Test]
    public function it_does_not_question_meta_capability_prompts(): void
    {
        $out = app(CouncilQuestionService::class)->analyze(
            'what are you good at?',
            ['workflow' => 'direct_answer'],
        );

        $this->assertFalse($out['needs_questions']);
        $this->assertSame([], $out['questions']);
    }

    #[Test]
    public function it_asks_for_audience_on_marketing_prompts_without_icp(): void
    {
        $out = app(CouncilQuestionService::class)->analyze(
            'Help me decide the best landing page positioning',
            ['workflow' => 'writer_only', 'skill' => 'marketing'],
        );

        $this->assertTrue($out['needs_questions']);
        $ids = array_column($out['questions'], 'id');
        $this->assertContains('audience', $ids);
    }

    #[Test]
    public function it_asks_for_offer_on_sales_outreach_without_product_context(): void
    {
        $out = app(CouncilQuestionService::class)->analyze(
            'Draft a cold sales outreach email',
            ['workflow' => 'writer_only', 'skill' => 'sales'],
        );

        $this->assertTrue($out['needs_questions']);
        $ids = array_column($out['questions'], 'id');
        $this->assertContains('offer', $ids);
    }

    #[Test]
    public function it_treats_recent_user_reply_as_answered_audience(): void
    {
        $out = app(CouncilQuestionService::class)->analyze(
            'Improve marketing for our landing page',
            ['workflow' => 'writer_only', 'skill' => 'marketing'],
            [
                ['role' => 'assistant', 'content' => 'Who is the audience?'],
                ['role' => 'user', 'content' => 'Enterprise teams in B2B SaaS'],
            ],
        );

        $this->assertFalse($out['needs_questions']);
        $this->assertTrue($out['already_answered']);
    }
}
