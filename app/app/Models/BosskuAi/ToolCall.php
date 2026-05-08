<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolCall extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_tool_calls';

    protected $fillable = [
        'run_id', 'run_step_id', 'tool', 'payload', 'result', 'status', 'error', 'latency_ms',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'result' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(RunStep::class, 'run_step_id');
    }
}
