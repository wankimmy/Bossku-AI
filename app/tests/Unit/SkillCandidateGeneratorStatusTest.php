<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\SkillCandidate;
use App\Services\Skills\SkillCandidateGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SkillCandidateGeneratorStatusTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pending_review_candidate_prevents_duplicate_auto_draft(): void
    {
        SkillCandidate::query()->create([
            'name' => 'checkout',
            'description' => 'Existing review candidate.',
            'approval_status' => 'pending_review',
        ]);
        Run::factory()->count(3)->create([
            'status' => 'completed',
            'selected_skill_name' => 'checkout',
        ]);

        $candidate = app(SkillCandidateGenerator::class)->maybeGenerate(
            Run::factory()->create([
                'status' => 'completed',
                'selected_skill_name' => 'checkout',
            ]),
        );

        $this->assertNull($candidate);
        $this->assertSame(1, SkillCandidate::query()->where('name', 'checkout')->count());
    }
}
