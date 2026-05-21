<?php

namespace App\Services\Graph;

use App\Models\BosskuAi\GraphEdge;
use App\Models\BosskuAi\GraphNode;
use Illuminate\Support\Str;

/**
 * Removes duplicate knowledge-graph nodes and edges so each source entity appears once.
 */
class KnowledgeGraphDedup
{
    /**
     * @return array{nodes_removed: int, edges_removed: int}
     */
    public function prune(): array
    {
        $nodesRemoved = $this->pruneNodesBySource();
        $nodesRemoved += $this->pruneMemoryNodesByLabel();
        $edgesRemoved = $this->pruneDuplicateEdges();

        return [
            'nodes_removed' => $nodesRemoved,
            'edges_removed' => $edgesRemoved,
        ];
    }

    protected function pruneNodesBySource(): int
    {
        $removed = 0;
        $groups = GraphNode::query()
            ->whereNotNull('source_type')
            ->whereNotNull('source_id')
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy(fn (GraphNode $node) => $node->source_type.'|'.$node->source_id);

        foreach ($groups as $nodes) {
            if ($nodes->count() <= 1) {
                continue;
            }
            $keep = $nodes->first();
            foreach ($nodes->slice(1) as $duplicate) {
                $this->rewireNodeEdges((string) $duplicate->getKey(), (string) $keep->getKey());
                $duplicate->delete();
                $removed++;
            }
        }

        return $removed;
    }

    protected function pruneMemoryNodesByLabel(): int
    {
        $removed = 0;
        $groups = GraphNode::query()
            ->where('type', 'memory')
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy(fn (GraphNode $node) => $this->normalizedLabelKey((string) $node->label));

        foreach ($groups as $labelKey => $nodes) {
            if ($labelKey === '' || $nodes->count() <= 1) {
                continue;
            }
            $keep = $nodes->first();
            foreach ($nodes->slice(1) as $duplicate) {
                $this->rewireNodeEdges((string) $duplicate->getKey(), (string) $keep->getKey());
                $duplicate->delete();
                $removed++;
            }
        }

        return $removed;
    }

    protected function pruneDuplicateEdges(): int
    {
        $removed = 0;
        $seen = [];
        $edges = GraphEdge::query()->orderByDesc('updated_at')->get();

        foreach ($edges as $edge) {
            $key = $edge->source_node_id.'|'.$edge->target_node_id.'|'.$edge->relation;
            if (isset($seen[$key])) {
                $edge->delete();
                $removed++;

                continue;
            }
            $seen[$key] = true;
        }

        return $removed;
    }

    protected function rewireNodeEdges(string $fromNodeId, string $toNodeId): void
    {
        if ($fromNodeId === $toNodeId) {
            return;
        }

        GraphEdge::query()
            ->where('source_node_id', $fromNodeId)
            ->update(['source_node_id' => $toNodeId]);

        GraphEdge::query()
            ->where('target_node_id', $fromNodeId)
            ->update(['target_node_id' => $toNodeId]);
    }

    protected function normalizedLabelKey(string $label): string
    {
        $normalized = Str::lower(trim(preg_replace('/\s+/', ' ', $label) ?? $label));

        return mb_substr($normalized, 0, 200);
    }
}
