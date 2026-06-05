<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReactionState extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_reaction_states';

    protected $fillable = [
        'run_id',
        'reaction_key',
        'attempts',
        'last_triggered_at',
        'escalated_at',
        'last_payload',
    ];

    protected function casts(): array
    {
        return [
            'last_payload' => 'array',
            'last_triggered_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }
}
