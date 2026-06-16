<?php

namespace App\Services\Kernel\Graph;

use App\Services\Kernel\Channels\BinaryOperatorAggregate;
use App\Services\Kernel\Channels\LastValue;
use App\Services\Kernel\Channels\Topic;
use App\Services\Kernel\Checkpoint\CheckpointSaverInterface;
use App\Services\Kernel\Constants;
use App\Services\Kernel\Nodes\NodeInterface;
use App\Services\Kernel\Runtime\RunState;

/**
 * Builds BosskuAI's current orchestration pipeline as a compiled graph:
 *
 *   START → router → memory → planner → executor
 *                 → {auditor?} → {security?} → {final?} → END
 *
 * The conditional edges read the classifier's `route` (the `workflow` string +
 * needs_* flags from DeterministicTaskClassifier / PromptRouteClassifier), so
 * routing is reused rather than reinvented and behavior matches the legacy
 * workflow matrix exactly. Nodes are injected so the topology is unit-testable
 * with fakes; the real node adapters wrap the existing pipeline services.
 */
final class DefaultPipelineGraph
{
    public const ROUTER = 'router';

    public const MEMORY = 'memory';

    public const PLANNER = 'planner';

    public const EXECUTOR = 'executor';

    public const AUDITOR = 'auditor';

    public const SECURITY = 'security';

    public const FINAL = 'final';

    public const NAME = 'default_pipeline';

    /** @return list<string> the node names this graph expects */
    public static function nodeNames(): array
    {
        return [
            self::ROUTER, self::MEMORY, self::PLANNER, self::EXECUTOR,
            self::AUDITOR, self::SECURITY, self::FINAL,
        ];
    }

    /**
     * Pure-data description of the graph topology for visual rendering (Studio).
     * No node instances required — safe to expose over the API.
     *
     * @return array<string, mixed>
     */
    public static function topology(): array
    {
        return [
            'name' => self::NAME,
            'entry' => self::ROUTER,
            'nodes' => self::nodeNames(),
            'edges' => [
                ['from' => self::ROUTER, 'to' => self::MEMORY],
                ['from' => self::MEMORY, 'to' => self::PLANNER],
                ['from' => self::PLANNER, 'to' => self::EXECUTOR],
                ['from' => self::FINAL, 'to' => Constants::END],
            ],
            'branches' => [
                ['from' => self::EXECUTOR, 'routes' => ['auditor' => self::AUDITOR, 'end' => Constants::END]],
                ['from' => self::AUDITOR, 'routes' => ['security' => self::SECURITY, 'final' => self::FINAL, 'end' => Constants::END]],
                ['from' => self::SECURITY, 'routes' => ['final' => self::FINAL, 'end' => Constants::END]],
            ],
        ];
    }

    /**
     * The channel shape of the pipeline blackboard.
     */
    public static function schema(): StateSchema
    {
        return StateSchema::make([
            'prompt' => new LastValue,        // raw user prompt
            'conversation' => new LastValue,  // prior turns
            'options' => new LastValue,       // run options
            'route' => new LastValue,         // classifier output: workflow + flags
            'memory' => new LastValue,        // retrieved memory context
            'plan' => new LastValue,          // planner ExecutionPlan
            'execution' => new LastValue,     // executor result
            'audit' => new Topic,             // accumulated audit findings
            'security' => new LastValue,      // security auditor result
            'final' => new LastValue,         // final reviewer summary
            'output' => new LastValue,        // composed user-facing output
            'messages' => new Topic,          // agent messages (append)
            'tokens' => new BinaryOperatorAggregate(fn ($a, $b) => (int) $a + (int) $b, 0),
        ]);
    }

    /**
     * @param  array<string, NodeInterface>  $nodes  keyed by the ROUTER..FINAL constants
     */
    public static function build(array $nodes, ?CheckpointSaverInterface $saver = null): CompiledGraph
    {
        $builder = new GraphBuilder(self::schema());

        foreach (self::nodeNames() as $name) {
            if (! isset($nodes[$name])) {
                throw new \InvalidArgumentException("DefaultPipelineGraph requires a node for '{$name}'.");
            }
            $builder->addNode($name, $nodes[$name]);
        }

        $builder
            ->setEntryPoint(self::ROUTER)
            ->addEdge(self::ROUTER, self::MEMORY)
            ->addEdge(self::MEMORY, self::PLANNER)
            ->addEdge(self::PLANNER, self::EXECUTOR)
            ->addConditionalEdges(self::EXECUTOR, self::afterExecutor(...), [
                'auditor' => self::AUDITOR,
                'end' => Constants::END,
            ])
            ->addConditionalEdges(self::AUDITOR, self::afterAuditor(...), [
                'security' => self::SECURITY,
                'final' => self::FINAL,
                'end' => Constants::END,
            ])
            ->addConditionalEdges(self::SECURITY, self::afterSecurity(...), [
                'final' => self::FINAL,
                'end' => Constants::END,
            ])
            ->addEdge(self::FINAL, Constants::END);

        return $builder->compile($saver);
    }

    private static function workflow(RunState $state): string
    {
        $route = $state->get('route', []);

        return is_array($route) ? (string) ($route['workflow'] ?? '') : '';
    }

    public static function afterExecutor(RunState $state): string
    {
        return str_contains(self::workflow($state), 'auditor') ? 'auditor' : 'end';
    }

    public static function afterAuditor(RunState $state): string
    {
        $workflow = self::workflow($state);
        if (str_contains($workflow, 'security')) {
            return 'security';
        }
        if (str_contains($workflow, 'final_reviewer')) {
            return 'final';
        }

        return 'end';
    }

    public static function afterSecurity(RunState $state): string
    {
        return str_contains(self::workflow($state), 'final_reviewer') ? 'final' : 'end';
    }
}
