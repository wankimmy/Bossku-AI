<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillLink extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_skill_links';

    protected $fillable = ['skill_id', 'link_type', 'linked_id', 'metadata'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }
}
