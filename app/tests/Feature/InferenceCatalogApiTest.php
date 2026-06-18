<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InferenceCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function inference_catalog_returns_ollama_models_always(): void
    {
        $this->getJson('/api/settings/inference-catalog')
            ->assertOk()
            ->assertJsonPath('anthropic_configured', false)
            ->assertJsonPath('codex_connected', false)
            ->assertJsonPath('cloud_only', true)
            ->assertJsonStructure([
                'ollama' => [['id', 'label']],
                'providers' => [['provider', 'name', 'configured']],
            ]);

        $ollama = $this->getJson('/api/settings/inference-catalog')->json('ollama');
        $this->assertNotEmpty($ollama);
    }

    #[Test]
    public function inference_catalog_includes_anthropic_when_key_configured(): void
    {
        Setting::setValue('anthropic_api_key_encrypted', Crypt::encryptString('sk-ant-test'));

        $this->getJson('/api/settings/inference-catalog')
            ->assertOk()
            ->assertJsonPath('anthropic_configured', true)
            ->assertJsonStructure([
                'anthropic' => [['id', 'label']],
            ]);

        $anthropic = $this->getJson('/api/settings/inference-catalog')->json('anthropic');
        $this->assertNotEmpty($anthropic);
    }
}
