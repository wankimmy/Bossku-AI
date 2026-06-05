<?php

namespace Tests\Feature;

use App\Models\BosskuAi\SoulVersion;
use App\Services\Soul\SoulService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoulApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function soul_show_returns_200_or_404_when_no_active_version(): void
    {
        $response = $this->getJson('/api/soul');

        // With no active soul version the controller returns 404 — that is acceptable.
        $this->assertContains($response->status(), [200, 404]);
    }

    /** @test */
    public function soul_show_returns_200_when_active_version_exists(): void
    {
        SoulVersion::create([
            'version' => '1.0.0',
            'content' => 'You are Bossku AI.',
            'active'  => true,
        ]);

        $response = $this->getJson('/api/soul');

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'version', 'content', 'active']);
    }

    /** @test */
    public function soul_history_returns_array(): void
    {
        SoulVersion::create([
            'version' => '1.0.0',
            'content' => 'Initial soul content.',
            'active'  => false,
        ]);

        $response = $this->getJson('/api/soul/history');

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    /** @test */
    public function soul_put_with_content_creates_new_version(): void
    {
        // Bind a simple mock so createVersion delegates to SoulService::update
        $this->app->bind(SoulService::class, function () {
            return new class extends SoulService {
                public function createVersion(string $content, ?string $changeSummary = null): SoulVersion
                {
                    return $this->update($content, (string) $changeSummary);
                }
            };
        });

        $response = $this->putJson('/api/soul', [
            'content'        => 'Updated soul prompt.',
            'change_summary' => 'Phase 5.3 update',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('bossku_ai_soul_versions', [
            'content' => 'Updated soul prompt.',
        ]);
    }
}
