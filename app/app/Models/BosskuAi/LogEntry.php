<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogEntry extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_logs';

    protected $fillable = [
        'level', 'channel', 'message', 'context', 'run_id', 'source',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }
}
