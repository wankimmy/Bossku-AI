<?php

namespace Tests\Feature;

use App\Models\BosskuAi\SkillCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillCandidatesApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function skill_candidates_index_returns_200(): void
    {
        $response = $this->getJson('/api/skill-candidates');

        $response->assertStatus(200);
    }

    /** @test */
    public function skill_candidates_reject_sets_approval_status_to_rejected(): void
    {
        $candidate = SkillCandidate::create([
            'name'            => 'Auto-generated Skill',
            'description'     => 'Handles payment retries.',
            'approval_status' => 'pending',
        ]);

        $response = $this->postJson("/api/skill-candidates/{$candidate->id}/reject", [
            'reason' => 'Not relevant',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('candidate.approval_status', 'rejected');

        $this->assertDatabaseHas('bossku_ai_skill_candidates', [
            'id'              => $candidate->id,
            'approval_status' => 'rejected',
        ]);
    }

    /** @test */
    public function skill_candidates_patch_updates_draft_content(): void
    {
        $candidate = SkillCandidate::create([
            'name'            => 'Retry Skill',
            'description'     => 'Handles retries.',
            'draft_content'   => 'Old draft content.',
            'approval_status' => 'pending',
        ]);

        $response = $this->patchJson("/api/skill-candidates/{$candidate->id}", [
            'draft_content' => 'Updated draft content with full instructions.',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['draft_content' => 'Updated draft content with full instructions.']);

        $this->assertDatabaseHas('bossku_ai_skill_candidates', [
            'id'            => $candidate->id,
            'draft_content' => 'Updated draft content with full instructions.',
        ]);
    }
}
