<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Model;

class AgentPersona extends Model
{
    protected $table = 'bossku_ai_agent_personas';

    protected $primaryKey = 'role';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'role',
        'display_name',
        'content',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
