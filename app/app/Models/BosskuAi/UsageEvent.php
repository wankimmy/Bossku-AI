<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageEvent extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_usage_events';

    protected $fillable = [
        'run_id', 'run_step_id', 'provider', 'model', 'role',
        'input_tokens', 'output_tokens', 'cost_usd', 'call_type', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost_usd' => 'decimal:8',
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
