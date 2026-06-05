<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Setting;
use App\Services\Data\DataExplorerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DataExplorerServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function allowed_tables_only_bossku_ai_prefix(): void
    {
        $svc = app(DataExplorerService::class);
        $tables = $svc->allowedTables();

        $this->assertNotEmpty($tables);
        foreach ($tables as $name) {
            $this->assertStringStartsWith('bossku_ai_', $name);
        }
        $this->assertNotContains('users', $tables);
    }

    #[Test]
    public function rejects_disallowed_table(): void
    {
        $svc = app(DataExplorerService::class);
        $this->expectException(\InvalidArgumentException::class);
        $svc->assertAllowedTable('users');
    }

    #[Test]
    public function masks_secret_settings_value(): void
    {
        Setting::query()->create([
            'key' => 'ollama_api_key_encrypted',
            'value' => 'super-secret',
        ]);

        $svc = app(DataExplorerService::class);
        $page = $svc->listRows('bossku_ai_settings', 1, 25, 'key', 'ollama_api_key_encrypted', 'asc');
        $row = collect($page['rows'])->firstWhere('key', 'ollama_api_key_encrypted');

        $this->assertNotNull($row);
        $this->assertSame('••••••••', $row['value'] ?? null);
    }
}
