<?php

namespace App\Services\Skills;

use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\Skill;
use App\Models\BosskuAi\SkillCandidate;
use Illuminate\Support\Str;

class SkillCandidateGenerator
{
    public function maybeGenerate(Run $run): ?SkillCandidate
    {
        $skillName = $run->selected_skill_name;
        if (blank($skillName)) {
            return null;
        }

        $recentCount = Run::where('status', 'completed')
            ->where('selected_skill_name', $skillName)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($recentCount < 3) {
            return null;
        }

        $exists = SkillCandidate::where('name', $skillName)
            ->whereIn('approval_status', ['draft', 'pending_review', 'approved'])
            ->exists();

        if ($exists) {
            return null;
        }

        $slug    = Str::slug($skillName);
        $content = $this->buildDraftContent($skillName);

        return SkillCandidate::create([
            'name'            => $skillName,
            'description'     => 'Auto-generated candidate from repeated use of ' . $skillName,
            'draft_content'   => $content,
            'approval_status' => 'draft',
            'source_run_count' => $recentCount,
            'source_run_ids'  => Run::where('status', 'completed')
                ->where('selected_skill_name', $skillName)
                ->where('created_at', '>=', now()->subDays(30))
                ->pluck('id')
                ->toArray(),
        ]);
    }

    public function approve(SkillCandidate $candidate, bool $exportToDisk = false): Skill
    {
        if ($candidate->isRiskyCategory()) {
            throw new \RuntimeException(
                'Skill candidate "' . $candidate->name . '" is in a risky category (' . $candidate->category . ') and must be manually reviewed before approval.'
            );
        }

        $skill = Skill::create([
            'name'            => $candidate->name,
            'description'     => $candidate->description,
            'content'         => $candidate->draft_content,
            'approval_status' => 'approved',
            'is_active'       => true,
            'quality_score'   => $candidate->quality_score,
        ]);

        $candidate->approval_status  = 'approved';
        $candidate->approved_skill_id = $skill->getKey();
        $candidate->reviewed_at      = now();
        $candidate->save();

        if ($exportToDisk) {
            $slug = Str::slug($candidate->name);
            $dir  = base_path('skills/' . $slug);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($dir . '/SKILL.md', (string) $candidate->draft_content);
        }

        return $skill;
    }

    public function reject(SkillCandidate $candidate, string $reason = ''): SkillCandidate
    {
        $candidate->approval_status = 'rejected';
        $candidate->reviewed_at     = now();
        if ($reason !== '') {
            $meta          = (array) ($candidate->metadata ?? []);
            $meta['rejection_reason'] = $reason;
            $candidate->metadata = $meta;
        }
        $candidate->save();

        return $candidate;
    }

    private function buildDraftContent(string $skillName): string
    {
        return <<<MD
# {$skillName}

## Description
Auto-generated skill for {$skillName}. Please update this description with accurate details.

## Rules
- Follow best practices for {$skillName}
- Validate inputs before processing
- Handle errors gracefully

## Instructions
1. Understand the context of the request
2. Apply {$skillName} techniques
3. Return structured output
MD;
    }
}
