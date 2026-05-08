<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Rule extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_rules';

    protected $fillable = [
        'scope', 'skill_name', 'name', 'rule_text', 'source_path',
        'priority', 'metadata', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }
}
