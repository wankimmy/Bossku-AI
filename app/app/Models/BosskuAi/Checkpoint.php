<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persisted graph-kernel checkpoint. thread_id == run id.
 */
class Checkpoint extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'bossku_ai_checkpoints';

    protected $fillable = [
        'id', 'thread_id', 'parent_id', 'channel_values', 'next', 'step', 'source', 'metadata', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'channel_values' => 'array',
            'next' => 'array',
            'metadata' => 'array',
            'step' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'thread_id');
    }
}
