<?php

namespace Tests\Unit;

use App\Models\BosskuAi\GraphEdge;
use App\Models\BosskuAi\GraphNode;
use App\Models\BosskuAi\Skill;
use Database\Seeders\BosskuAiSpecSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BosskuAiSpecSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function seeder_is_idempotent_for_skill_graph_nodes(): void
    {
        $skill = Skill::create([
            'name' => 'laravel-api',
            'description' => 'Existing skill from import.',
            'content' => '# laravel-api',
            'is_active' => true,
        ]);

        GraphNode::create([
            'type' => 'skill',
            'label' => 'laravel-api',
            'source_type' => 'skill',
            'source_id' => $skill->getKey(),
            'confidence' => 0.9,
            'has_conflict' => false,
            'properties' => ['quality_score' => 80],
        ]);

        $this->seed(BosskuAiSpecSeeder::class);
        $this->seed(BosskuAiSpecSeeder::class);

        $this->assertSame(
            1,
            GraphNode::where('source_type', 'skill')->where('source_id', $skill->getKey())->count()
        );
        $this->assertTrue(
            (bool) GraphNode::where('source_id', Skill::where('name', 'debug-php')->value('id'))
                ->value('has_conflict')
        );
        $this->assertGreaterThanOrEqual(1, GraphEdge::where('relation', 'conflicts_with')->count());
    }
}
