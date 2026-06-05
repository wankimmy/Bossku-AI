<?php

namespace Tests\Unit;

use App\Support\StringCoercion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StringCoercionTest extends TestCase
{
    #[Test]
    public function it_converts_array_patch_summary_to_multiline_string(): void
    {
        $text = StringCoercion::toString([
            'Updated routes/api.php',
            'Added health check',
        ]);

        $this->assertStringContainsString('Updated routes/api.php', $text);
        $this->assertStringContainsString('Added health check', $text);
    }

    #[Test]
    public function it_preserves_plain_strings(): void
    {
        $this->assertSame('hello', StringCoercion::toString('hello'));
    }
}
