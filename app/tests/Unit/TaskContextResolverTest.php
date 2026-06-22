<?php

namespace Tests\Unit;

use App\Support\TaskContextResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaskContextResolverTest extends TestCase
{
    #[Test]
    public function anaphoric_follow_up_uses_routing_prompt(): void
    {
        $conversation = [
            ['role' => 'user', 'content' => 'read docs/PRODUCT_SPEC.md'],
            ['role' => 'assistant', 'content' => 'I can read that file for you.'],
        ];
        $routingPrompt = TaskContextResolver::buildEffectivePrompt('ok read it', $conversation);

        $input = TaskContextResolver::routingInput('ok read it', $routingPrompt, $conversation);

        $this->assertStringContainsString('docs/PRODUCT_SPEC.md', $input);
        $this->assertStringContainsString('ok read it', $input);
    }

    #[Test]
    public function meta_question_ignores_polluted_history(): void
    {
        $conversation = [
            ['role' => 'user', 'content' => 'audit payment checkout in my project'],
        ];
        $routingPrompt = TaskContextResolver::buildEffectivePrompt('what are you good at?', $conversation);

        $input = TaskContextResolver::routingInput('what are you good at?', $routingPrompt, $conversation);

        $this->assertSame('what are you good at?', $input);
    }

    #[Test]
    public function social_ok_stays_isolated(): void
    {
        $conversation = [
            ['role' => 'user', 'content' => 'read docs/PRODUCT_SPEC.md'],
        ];

        $this->assertTrue(TaskContextResolver::isSocialAcknowledgement('ok'));
        $this->assertFalse(TaskContextResolver::isAnaphoricFollowUp('ok', $conversation));
    }

    #[Test]
    public function recent_path_survives_truncation(): void
    {
        $conversation = [];
        $conversation[] = ['role' => 'user', 'content' => str_repeat('older context ', 2000)];
        $conversation[] = ['role' => 'assistant', 'content' => str_repeat('long answer ', 2000)];
        $conversation[] = ['role' => 'user', 'content' => 'read docs/PRODUCT_SPEC.md'];
        $conversation[] = ['role' => 'assistant', 'content' => 'Ready when you are.'];
        $conversation[] = ['role' => 'user', 'content' => 'ok read it'];

        $built = TaskContextResolver::buildEffectivePrompt('ok read it', $conversation);

        $this->assertStringContainsString('docs/PRODUCT_SPEC.md', $built);
        $this->assertStringContainsString('ok read it', $built);
    }

    #[Test]
    public function extract_context_anchors_finds_docs_target(): void
    {
        $conversation = [
            ['role' => 'user', 'content' => 'read docs/PRODUCT_SPEC.md'],
        ];

        $anchors = TaskContextResolver::extractContextAnchors('proceed', $conversation, 'Safwan');

        $this->assertContains('docs/PRODUCT_SPEC.md', $anchors['docs_targets']);
        $this->assertSame('docs_read', $anchors['task_kind']);
        $this->assertSame('Safwan', $anchors['active_repo']);
    }
}
