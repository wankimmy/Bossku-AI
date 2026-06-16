<?php

namespace App\Services\Kernel\Nodes;

use App\Services\Kernel\Runtime\GraphContext;
use App\Services\Kernel\Runtime\RunState;
use App\Services\Kernel\Types\Command;

/**
 * Adapts any callable (RunState, GraphContext) => array|Command into a node.
 * Lets graphs be assembled from closures and makes the pipeline-stage adapters
 * trivial to write and test.
 */
final class CallableNode implements NodeInterface
{
    /** @var callable(RunState, GraphContext): (array<string, mixed>|Command) */
    private $fn;

    public function __construct(callable $fn)
    {
        $this->fn = $fn;
    }

    public function invoke(RunState $state, GraphContext $ctx): array|Command
    {
        return ($this->fn)($state, $ctx);
    }
}
