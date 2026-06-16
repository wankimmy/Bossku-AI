<?php

namespace App\Services\Kernel\Nodes;

use App\Services\Kernel\Runtime\GraphContext;
use App\Services\Kernel\Runtime\RunState;
use App\Services\Kernel\Types\Command;

/**
 * A graph node — the unit the scheduler runs. Pipeline-stage adapters (planner,
 * executor, auditor, ...) implement this and delegate to the existing services,
 * so no agent logic is rewritten.
 */
interface NodeInterface
{
    /**
     * @return array<string, mixed>|Command channel updates, or a Command to
     *                                       control routing + state together
     */
    public function invoke(RunState $state, GraphContext $ctx): array|Command;
}
