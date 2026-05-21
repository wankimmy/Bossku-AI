<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Memory extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_memories';

    protected $fillable = [
        'type', 'content', 'human_summary', 'metadata', 'tags',
        'confidence', 'source', 'is_active', 'last_used_at', 'usage_count',
        'conflicting_memory_ids_json',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'tags' => 'array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'confidence'                  => 'float',
            'conflicting_memory_ids_json' => 'array',
        ];
    }

    /** @var string|null raw vector for select only */
    public $embeddingVector;

    public function runLinks(): HasMany
    {
        return $this->hasMany(MemoryRunLink::class, 'memory_id');
    }
}
