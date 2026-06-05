<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoryRunLink extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_memory_run_links';

    protected $fillable = ['memory_id', 'run_id', 'similarity_score'];

    protected function casts(): array
    {
        return [
            'similarity_score' => 'float',
        ];
    }

    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class, 'memory_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }
}
