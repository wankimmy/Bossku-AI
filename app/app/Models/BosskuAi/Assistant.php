<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assistant extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_assistants';

    protected $fillable = [
        'name', 'slug', 'graph', 'config', 'description', 'enabled', 'metadata',
    ];

    protected $attributes = [
        'enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'metadata' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function cronJobs(): HasMany
    {
        return $this->hasMany(CronJob::class, 'assistant_id');
    }

    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class, 'assistant_id');
    }
}
