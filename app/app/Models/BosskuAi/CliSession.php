<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CliSession extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_cli_sessions';

    protected $fillable = [
        'run_id',
        'provider',
        'status',
        'external_session_id',
        'command',
        'metadata',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }
}
