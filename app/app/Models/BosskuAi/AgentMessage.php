<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMessage extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_agent_messages';

    protected $fillable = [
        'run_id', 'run_step_id', 'agent', 'provider', 'model', 'role',
        'skill', 'memory_used', 'safe_reasoning_summary', 'content', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'memory_used' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }

    public function runStep(): BelongsTo
    {
        return $this->belongsTo(RunStep::class, 'run_step_id');
    }
}
