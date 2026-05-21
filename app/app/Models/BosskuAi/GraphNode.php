<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GraphNode extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_knowledge_graph_nodes';

    protected $fillable = [
        'type', 'label', 'source_id', 'source_type',
        'confidence', 'has_conflict', 'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'has_conflict' => 'boolean',
        ];
    }

    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(GraphEdge::class, 'source_node_id');
    }

    public function incomingEdges(): HasMany
    {
        return $this->hasMany(GraphEdge::class, 'target_node_id');
    }
}
