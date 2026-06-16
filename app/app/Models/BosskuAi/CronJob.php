<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CronJob extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_cron_jobs';

    protected $fillable = [
        'assistant_id', 'name', 'cron_expression', 'prompt', 'payload',
        'enabled', 'last_run_at', 'next_run_at', 'metadata',
    ];

    protected $attributes = [
        'enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'metadata' => 'array',
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class, 'assistant_id');
    }
}
