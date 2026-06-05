<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_plugins';

    protected $fillable = [
        'name', 'slug', 'description', 'version', 'author',
        'permissions', 'manifest', 'is_active', 'last_heartbeat_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'manifest' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'last_heartbeat_at' => 'datetime',
        ];
    }
}
