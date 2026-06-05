<?php

namespace App\Services\Graph;

use App\Models\BosskuAi\GraphEdge;
use App\Models\BosskuAi\GraphNode;
use App\Models\BosskuAi\Skill;

class KnowledgeGraphPresenter
{
    public function __construct(
        private readonly KnowledgeGraphDedup $dedup,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(): array
    {
        $this->dedup->prune();

        $dbNodes = GraphNode::query()->get();
        $dbEdges = GraphEdge::query()->get();

        $skillDescriptions = Skill::query()
            ->get(['id', 'name', 'description'])
            ->keyBy('id');

        $deepMin = (int) config('bossku_graph.depth_deep_min', 250);
        $okMin = (int) config('bossku_graph.depth_ok_min', 100);

        $nodes = [];
        foreach ($dbNodes as $node) {
            $props = (array) ($node->properties ?? []);
            $type = (string) ($node->type ?? 'unknown');
            $desc = '';

            if ($node->source_type === 'skill' && $node->source_id) {
                $skill = $skillDescriptions->get($node->source_id);
                $desc = (string) ($skill?->description ?? '');
            }
            if ($desc === '' && isset($props['description'])) {
                $desc = (string) $props['description'];
            }

            $quality = $props['quality_score'] ?? null;
            $confidence = $node->confidence !== null ? (float) $node->confidence : null;
            $depthScore = is_numeric($quality) ? (float) $quality * 100 : ($confidence !== null ? $confidence * 100 : 50);

            $nodes[] = [
                'id' => $node->getKey(),
                'label' => (string) $node->label,
                'category' => $type,
                'type' => $type,
                'is_marquee' => false,
                'is_core' => false,
                'depth' => $this->depthFromScore($depthScore, $deepMin, $okMin),
                'skill_lines' => 0,
                'playbook_lines' => 0,
                'total_lines' => (int) round($depthScore),
                'triggers' => [],
                'keywords' => [],
                'trigger_count' => 0,
                'description' => mb_substr($desc, 0, 300),
                'playbook_refs' => [],
                'has_conflict' => (bool) $node->has_conflict,
                'confidence' => $confidence,
                'source_type' => $node->source_type,
                'source_id' => $node->source_id,
                'properties' => $props,
            ];
        }

        $edges = [];
        foreach ($dbEdges as $edge) {
            $relation = (string) ($edge->relation ?? 'related');
            $edges[] = [
                'source' => (string) $edge->source_node_id,
                'target' => (string) $edge->target_node_id,
                'kind' => $relation,
                'is_conflict' => (bool) $edge->is_conflict,
                'weight' => $edge->weight,
            ];
        }

        $byCat = [];
        foreach ($nodes as $n) {
            $cat = $n['category'];
            $byCat[$cat] = ($byCat[$cat] ?? 0) + 1;
        }

        return [
            'version' => 'knowledge-db',
            'node_count' => count($nodes),
            'edge_count' => count($edges),
            'categories' => $byCat,
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    private function depthFromScore(float $score, int $deepMin, int $okMin): string
    {
        if ($score >= $deepMin) {
            return 'DEEP';
        }
        if ($score >= $okMin) {
            return 'OK';
        }

        return 'THIN';
    }
}
