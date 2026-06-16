<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pending per-task channel write for crash recovery of a partial superstep.
 */
class CheckpointWrite extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'bossku_ai_checkpoint_writes';

    protected $fillable = [
        'checkpoint_id', 'task_id', 'idx', 'channel', 'value', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'idx' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(Checkpoint::class, 'checkpoint_id');
    }
}
