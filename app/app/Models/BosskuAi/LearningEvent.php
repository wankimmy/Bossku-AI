<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningEvent extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_learning_events';

    protected $fillable = [
        'run_id', 'type', 'content', 'status',
        'confidence', 'evidence', 'metadata', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'metadata' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }
}
