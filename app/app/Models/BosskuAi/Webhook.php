<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_webhooks';

    protected $fillable = [
        'url', 'events', 'secret', 'enabled', 'metadata',
    ];

    protected $attributes = [
        'enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'metadata' => 'array',
            'enabled' => 'boolean',
        ];
    }

    protected $hidden = [
        'secret',
    ];
}
