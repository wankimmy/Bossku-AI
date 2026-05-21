<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraphEdge extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_knowledge_graph_edges';

    protected $fillable = [
        'source_node_id', 'target_node_id', 'relation',
        'weight', 'is_conflict', 'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'is_conflict' => 'boolean',
        ];
    }

    public function sourceNode(): BelongsTo
    {
        return $this->belongsTo(GraphNode::class, 'source_node_id');
    }

    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(GraphNode::class, 'target_node_id');
    }
}
