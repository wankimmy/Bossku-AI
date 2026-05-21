<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelRoute extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_model_routes';

    protected $fillable = [
        'role', 'primary_provider_id', 'primary_model',
        'fallback_provider_id', 'fallback_model',
        'routing_rules_json', 'monthly_budget_usd', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'routing_rules_json' => 'array',
            'monthly_budget_usd' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function primaryProvider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'primary_provider_id');
    }

    public function fallbackProvider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'fallback_provider_id');
    }
}
