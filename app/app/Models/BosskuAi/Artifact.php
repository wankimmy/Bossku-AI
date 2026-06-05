<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Artifact extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_artifacts';

    protected $fillable = [
        'type', 'name', 'description', 'content', 'source_path',
        'metadata', 'tags', 'token_estimate', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'tags' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
