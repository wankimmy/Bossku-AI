<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Playbook extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_playbooks';

    protected $fillable = [
        'name', 'description', 'content', 'source_path', 'tags', 'metadata', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
