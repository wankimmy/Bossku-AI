<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Skill;
use App\Services\Skills\SkillQualityScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SkillQualityScorerTest extends TestCase
{
    use RefreshDatabase;

    private function makeScorer(): SkillQualityScorer
    {
        return new SkillQualityScorer();
    }

    #[Test]
    public function skill_with_no_description_and_no_content_scores_zero(): void
    {
        $skill = new Skill([
            'name'    => 'Empty Skill',
            'content' => null,
        ]);

        $score = $this->makeScorer()->score($skill);

        $this->assertSame(0.0, $score);
    }

    #[Test]
    public function skill_with_description_and_content_and_rules_scores_above_50(): void
    {
        $skill = new Skill([
            'name'        => 'Well Documented Skill',
            'description' => 'This skill handles payment webhook validation with full error handling and retry logic.',
            'content'     => str_repeat('Detailed skill content that is longer than 200 characters. ', 5),
            'rules'       => ['rule1', 'rule2'],
        ]);

        $score = $this->makeScorer()->score($skill);

        $this->assertGreaterThan(50, $score);
    }

    #[Test]
    public function score_and_save_updates_skill_quality_score(): void
    {
        $skill = Skill::create([
            'name'        => 'Saveable Skill',
            'description' => 'A skill description that is longer than fifty characters total.',
            'content'     => str_repeat('Content chunk. ', 20),
            'rules'       => ['validate', 'retry'],
        ]);

        $scorer = $this->makeScorer();
        $score  = $scorer->scoreAndSave($skill);

        $this->assertGreaterThan(0, $score);

        $skill->refresh();
        $this->assertEqualsWithDelta($score, (float) $skill->quality_score, 0.001);
        $this->assertNotNull($skill->quality_score);
    }

    #[Test]
    public function skill_with_only_content_below_200_chars_scores_correctly(): void
    {
        $skill = new Skill([
            'name'    => 'Short Skill',
            'content' => 'Short content.',
        ]);

        // No description, content < 200 chars — score should be 0 (blank content branch does not apply,
        // but no description bonus, no long content bonus, so score remains 0.0)
        $score = $this->makeScorer()->score($skill);

        $this->assertSame(0.0, $score);
    }
}
