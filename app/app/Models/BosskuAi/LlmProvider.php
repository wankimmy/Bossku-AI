<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class LlmProvider extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_llm_providers';

    protected $fillable = [
        'name', 'slug', 'type', 'base_url', 'api_key_encrypted', 'api_key_env',
        'available_models', 'routing_rules_json', 'is_active',
        'last_health_check_at', 'health_status', 'metadata',
    ];

    protected $hidden = ['api_key_encrypted'];

    protected function casts(): array
    {
        return [
            'available_models' => 'array',
            'routing_rules_json' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'last_health_check_at' => 'datetime',
        ];
    }

    public function modelRoutes(): HasMany
    {
        return $this->hasMany(ModelRoute::class, 'primary_provider_id');
    }

    public function resolveApiKey(): ?string
    {
        if ($this->api_key_env) {
            return env($this->api_key_env);
        }

        if ($this->api_key_encrypted) {
            return Crypt::decryptString($this->api_key_encrypted);
        }

        return null;
    }

    public function setApiKey(string $key): void
    {
        $this->api_key_encrypted = Crypt::encryptString($key);
    }
}
