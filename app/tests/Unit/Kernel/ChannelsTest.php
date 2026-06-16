<?php

namespace Tests\Unit\Kernel;

use App\Services\Kernel\Channels\BinaryOperatorAggregate;
use App\Services\Kernel\Channels\EphemeralValue;
use App\Services\Kernel\Channels\LastValue;
use App\Services\Kernel\Channels\Topic;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ChannelsTest extends TestCase
{
    #[Test]
    public function last_value_keeps_the_final_write(): void
    {
        $c = new LastValue;
        $this->assertFalse($c->isSet());
        $c->update('a');
        $c->update('b');
        $this->assertTrue($c->isSet());
        $this->assertSame('b', $c->get());
    }

    #[Test]
    public function last_value_round_trips_through_checkpoint(): void
    {
        $c = new LastValue;
        $c->update(['plan' => 1]);
        $snap = $c->checkpoint();

        $restored = new LastValue;
        $restored->restore($snap);
        $this->assertSame(['plan' => 1], $restored->get());
    }

    #[Test]
    public function topic_appends_and_supports_unique(): void
    {
        $c = new Topic(unique: true);
        $c->update('x');
        $c->update(['x', 'y']);
        $this->assertSame(['x', 'y'], $c->get());
    }

    #[Test]
    public function binary_operator_folds_from_initial(): void
    {
        $c = new BinaryOperatorAggregate(fn ($a, $b) => $a + $b, 0);
        $c->update(3);
        $c->update(4);
        $this->assertSame(7, $c->get());

        $restored = new BinaryOperatorAggregate(fn ($a, $b) => $a + $b, 0);
        $restored->restore($c->checkpoint());
        $restored->update(1);
        $this->assertSame(8, $restored->get());
    }

    #[Test]
    public function ephemeral_value_clears_on_consume(): void
    {
        $c = new EphemeralValue;
        $c->update('scratch');
        $this->assertTrue($c->isSet());
        $this->assertTrue($c->consume());
        $this->assertFalse($c->isSet());
        $this->assertNull($c->get());
    }
}
