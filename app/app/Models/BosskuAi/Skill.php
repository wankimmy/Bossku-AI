<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Skill extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_skills';

    protected $fillable = [
        'name', 'description', 'rules', 'tools', 'playbooks', 'checklists',
        'source_path', 'content', 'metadata', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'tools' => 'array',
            'playbooks' => 'array',
            'checklists' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function links(): HasMany
    {
        return $this->hasMany(SkillLink::class, 'skill_id');
    }
}
