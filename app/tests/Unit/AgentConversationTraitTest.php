<?php

namespace Tests\Unit;

use App\Services\Orchestrator\AgentConversationTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AgentConversationTraitTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new class {
            use AgentConversationTrait;

            public function get(array $conversation): string
            {
                return $this->buildConversationBlock($conversation);
            }
        };
    }

    #[Test]
    public function it_returns_placeholder_when_conversation_is_empty(): void
    {
        $out = $this->subject->get([]);
        $this->assertStringContainsString('no prior conversation', $out);
    }

    #[Test]
    public function it_passes_through_short_conversations_verbatim(): void
    {
        $turns = [
            ['role' => 'user', 'content' => 'Hello world'],
            ['role' => 'assistant', 'content' => 'Hi there'],
        ];
        $out = $this->subject->get($turns);
        $this->assertStringContainsString('Hello world', $out);
        $this->assertStringContainsString('Hi there', $out);
        $this->assertStringNotContainsString('compressed', $out);
    }

    #[Test]
    public function it_compresses_older_turns_when_conversation_exceeds_keep_window(): void
    {
        $turns = [];
        for ($i = 0; $i < 15; $i++) {
            $turns[] = ['role' => 'user', 'content' => 'payment refund question '.($i + 1)];
            $turns[] = ['role' => 'assistant', 'content' => 'answer '.($i + 1)];
        }
        $out = $this->subject->get($turns);
        $this->assertStringContainsString('compressed', $out);
        $this->assertStringContainsString('payment', $out);
        // Most-recent turns must still appear verbatim
        $this->assertStringContainsString('question 15', $out);
    }

    #[Test]
    public function it_keeps_exactly_ten_recent_turns_in_full_detail(): void
    {
        $turns = [];
        for ($i = 0; $i < 20; $i++) {
            $turns[] = ['role' => 'user', 'content' => 'msg '.$i];
        }
        $out = $this->subject->get($turns);
        // Turn 19 is the last — must appear
        $this->assertStringContainsString('msg 19', $out);
        // Turn 9 is the oldest verbatim turn (last 10 of 20 = turns 10-19)
        $this->assertStringContainsString('msg 10', $out);
        // Turn 9 should be in the summary, not the verbatim block
        $this->assertStringNotContainsString('[Turn 9]', $out);
    }
}
