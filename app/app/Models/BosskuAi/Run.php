<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Run extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_runs';

    protected $fillable = [
        'prompt', 'final_output', 'status', 'total_latency_ms',
        'total_token_estimate', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(RunStep::class, 'run_id')->orderBy('step_number');
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(ToolCall::class, 'run_id');
    }

    public function memoryLinks(): HasMany
    {
        return $this->hasMany(MemoryRunLink::class, 'run_id');
    }
}
