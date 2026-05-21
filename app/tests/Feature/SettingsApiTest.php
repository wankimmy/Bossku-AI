<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Setting;
use App\Services\BosskuAi\RuntimeSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function settings_update_accepts_payload_without_ollama_api_key(): void
    {
        $this->putJson('/api/settings', [
            'router_model' => 'kimi-k2.6:cloud',
            'ollama_base_url' => 'http://host.docker.internal:11434',
            'max_memory_results' => 5,
            'audit_enabled' => '1',
            'model_aliases' => ['kimi-k2.6' => 'kimi-k2.6:cloud'],
        ])
            ->assertOk()
            ->assertJsonPath('router_model', 'kimi-k2.6:cloud')
            ->assertJsonPath('ollama_base_url', 'http://host.docker.internal:11434');
    }

    #[Test]
    public function settings_update_stores_optional_ollama_api_key_encrypted(): void
    {
        $this->putJson('/api/settings', [
            'ollama_api_key' => 'test-secret-key-12345',
            'ollama_base_url' => 'https://ollama.com',
        ])->assertOk();

        $encrypted = Setting::getValue('ollama_api_key_encrypted');
        $this->assertNotNull($encrypted);
        $this->assertSame('test-secret-key-12345', Crypt::decryptString((string) $encrypted));

        $settings = app(RuntimeSettings::class);
        $this->assertSame('test-secret-key-12345', $settings->ollamaApiKey());
        $this->assertNotNull($settings->ollamaApiKeyMasked());
    }

    #[Test]
    public function settings_update_accepts_model_aliases_json_string_from_legacy_payload(): void
    {
        $aliasesJson = '{"kimi-k2.6":"kimi-k2.6:cloud","glm-5.1":"glm-5.1:cloud"}';

        $this->putJson('/api/settings', [
            'planner_provider' => 'ollama',
            'planner_model' => 'kimi-k2.6:cloud',
            'model_aliases' => $aliasesJson,
            'allowed_cloud_models' => '["kimi-k2.6:cloud"]',
            'ollama_api_key' => '',
            'ollama_api_key_masked' => '0631…6Iqn',
            'max_memory_results' => '5',
        ])
            ->assertOk()
            ->assertJsonPath('planner_model', 'kimi-k2.6:cloud');

        $stored = json_decode((string) Setting::getValue('model_aliases'), true);
        $this->assertIsArray($stored);
        $this->assertSame('kimi-k2.6:cloud', $stored['kimi-k2.6'] ?? null);
    }

    #[Test]
    public function settings_update_ignores_masked_placeholder_for_api_key(): void
    {
        Setting::setValue('ollama_api_key_encrypted', Crypt::encryptString('keep-me'));

        $this->putJson('/api/settings', [
            'ollama_api_key' => '••••••••',
            'router_model' => 'glm-5.1:cloud',
        ])->assertOk();

        $this->assertSame('keep-me', Crypt::decryptString((string) Setting::getValue('ollama_api_key_encrypted')));
    }
}
