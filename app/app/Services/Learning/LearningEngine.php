<?php

namespace App\Services\Learning;

use App\Models\BosskuAi\FeedbackItem;
use App\Models\BosskuAi\LearningEvent;
use App\Models\BosskuAi\Run;
use Illuminate\Support\Str;

class LearningEngine
{
    /**
     * @param  array<string, mixed>  $execResult
     * @param  array<string, mixed>  $lastAudit
     */
    public function extractFromRun(Run $run, array $execResult = [], array $lastAudit = []): array
    {
        $extractions = [];
        $meta = is_array($run->metadata) ? $run->metadata : [];
        $plan = is_array($meta['plan'] ?? null) ? $meta['plan'] : [];

        // Rich lesson from successful run
        if ($run->status === 'completed') {
            $planSummary = Str::limit((string) ($plan['summary'] ?? $plan['task_summary'] ?? ''), 800);
            $execSummary = Str::limit((string) ($execResult['patch_summary'] ?? ''), 800);
            $auditSummary = Str::limit((string) ($lastAudit['summary'] ?? ''), 600);
            $filesChanged = is_array($execResult['files_changed'] ?? null)
                ? implode(', ', array_slice(array_map(
                    fn ($f) => is_array($f) ? ($f['path'] ?? '') : (string) $f,
                    $execResult['files_changed']
                ), 0, 10))
                : '';
            $knownIssues = is_array($execResult['known_issues'] ?? null)
                ? implode('; ', array_slice($execResult['known_issues'], 0, 5))
                : '';

            $content = trim(implode("\n", array_filter([
                'Task: '.$planSummary,
                $execSummary !== '' ? 'Execution: '.$execSummary : '',
                $auditSummary !== '' ? 'Audit: '.$auditSummary : '',
                $filesChanged !== '' ? 'Files changed: '.$filesChanged : '',
                $knownIssues !== '' ? 'Known issues: '.$knownIssues : '',
                'Skill: '.($plan['selected_skill'] ?? $run->selected_skill_name ?? 'general'),
                'Audit status: '.($lastAudit['status'] ?? 'not_run'),
            ])));

            if ($content !== '') {
                $extractions[] = [
                    'type'       => 'pattern',
                    'content'    => $content,
                    'confidence' => (float) ($run->audit_score > 0 ? $run->audit_score : 0.72),
                    'importance' => 0.65,
                    'evidence'   => [
                        'run_id' => $run->getKey(),
                        'audit_status' => $lastAudit['status'] ?? null,
                        'files_changed_count' => count($execResult['files_changed'] ?? []),
                    ],
                ];
            }
        }

        // Rich lesson from failed run
        if (in_array($run->status, ['failed', 'partial'], true)) {
            $planSummary = Str::limit((string) ($plan['summary'] ?? $plan['task_summary'] ?? $run->prompt ?? ''), 800);
            $execSummary = Str::limit((string) ($execResult['patch_summary'] ?? ''), 800);
            $auditSummary = Str::limit((string) ($lastAudit['summary'] ?? ''), 600);
            $knownIssues = is_array($execResult['known_issues'] ?? null)
                ? implode('; ', array_slice($execResult['known_issues'], 0, 5))
                : '';
            $auditFixes = is_array($lastAudit['required_fixes'] ?? null)
                ? implode('; ', array_slice($lastAudit['required_fixes'], 0, 5))
                : '';

            $content = trim(implode("\n", array_filter([
                'FAILED TASK: '.$planSummary,
                $execSummary !== '' ? 'Execution attempted: '.$execSummary : '',
                $auditSummary !== '' ? 'Audit findings: '.$auditSummary : '',
                $knownIssues !== '' ? 'Known issues: '.$knownIssues : '',
                $auditFixes !== '' ? 'Required fixes: '.$auditFixes : '',
                'Status: '.$run->status,
            ])));

            if ($content !== '') {
                $extractions[] = [
                    'type'       => 'failure',
                    'content'    => $content,
                    'confidence' => 0.90,
                    'importance' => 0.85,
                    'evidence'   => [
                        'run_id' => $run->getKey(),
                        'status' => $run->status,
                        'audit_status' => $lastAudit['status'] ?? null,
                    ],
                ];
            }
        }

        $thumbsUp = FeedbackItem::where('target_type', 'run')
            ->where('target_id', $run->getKey())
            ->where('signal', 'thumbs_up')
            ->exists();

        if ($thumbsUp) {
            $extractions[] = [
                'type'       => 'preference',
                'content'    => 'User expressed positive preference for run approach: '.($run->selected_skill_name ?? 'unknown skill'),
                'confidence' => 0.7,
                'importance' => 0.65,
                'evidence'   => ['run_id' => $run->getKey(), 'signal' => 'thumbs_up'],
            ];
        }

        return $extractions;
    }

    public function saveEvents(Run $run, array $extractions): void
    {
        foreach ($extractions as $extraction) {
            LearningEvent::create([
                'run_id'     => $run->getKey(),
                'type'       => $extraction['type'],
                'content'    => $extraction['content'],
                'confidence' => $extraction['confidence'],
                'evidence'   => $extraction['evidence'],
                'status'     => 'pending',
            ]);
        }
    }

}
