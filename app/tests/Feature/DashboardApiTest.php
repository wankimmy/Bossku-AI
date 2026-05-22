<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function dashboard_returns_200_with_stats(): void
    {
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'stats' => ['total_runs', 'skills_count'],
            ]);
    }

    /** @test */
    public function dashboard_stats_total_runs_is_integer(): void
    {
        Run::create([
            'status' => 'completed',
            'prompt' => 'Test prompt',
        ]);

        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);

        $stats = $response->json('stats');
        $this->assertIsInt($stats['total_runs']);
        $this->assertSame(Run::query()->count(), $stats['total_runs']);
    }

    /** @test */
    public function dashboard_stats_skills_count_is_integer(): void
    {
        Skill::create([
            'name'    => 'Test Skill',
            'content' => 'Skill content here.',
        ]);

        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);

        $stats = $response->json('stats');
        $this->assertIsInt($stats['skills_count']);
        $this->assertSame(1, $stats['skills_count']);
    }

    /** @test */
    public function dashboard_stats_are_all_integers_when_empty(): void
    {
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);

        $stats = $response->json('stats');
        $this->assertIsInt($stats['total_runs']);
        $this->assertIsInt($stats['skills_count']);
        $this->assertSame(Run::query()->count(), $stats['total_runs']);
        $this->assertSame(Skill::query()->count(), $stats['skills_count']);
    }
}
