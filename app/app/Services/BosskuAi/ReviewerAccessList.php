<?php

namespace App\Services\BosskuAi;

/**
 * Builds the bounded "access list" of prior-stage outputs handed to the Final Reviewer.
 *
 * Sakana Fugu isolates each agent so it sees prior work only through an explicit access
 * list, rather than the whole transcript — both to avoid orchestration collapse and to
 * keep context small. The aggregator here already receives a curated digest; this makes
 * the selection explicit and *bounded*: findings are ranked by severity and capped, so a
 * large audit cannot blow the reviewer's context window or bury the critical findings.
 */
class ReviewerAccessList
{
    private const SEVERITY_RANK = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

    /**
     * @param  array<string, mixed>  $auditor
     * @param  array<string, mixed>  $executorResult
     * @param  array<string, mixed>|null  $securityAudit
     * @return array<string, mixed>
     */
    public function forFinalReviewer(array $auditor, array $executorResult, ?array $securityAudit): array
    {
        $maxFindings = (int) config('bossku.reviewer_access_list.max_findings', 12);
        $maxFixes = (int) config('bossku.reviewer_access_list.max_required_fixes', 10);
        $maxFiles = (int) config('bossku.reviewer_access_list.max_files', 25);

        $findings = is_array($auditor['findings'] ?? null) ? $auditor['findings'] : [];
        $rankedFindings = $this->rankBySeverity($findings);
        $findingsTrimmed = max(0, count($rankedFindings) - $maxFindings);

        $files = is_array($executorResult['files_changed'] ?? null) ? $executorResult['files_changed'] : [];

        return [
            'auditor_status' => $auditor['status'] ?? null,
            'auditor_summary' => $auditor['summary'] ?? null,
            'auditor_findings' => array_slice($rankedFindings, 0, $maxFindings),
            'auditor_findings_omitted' => $findingsTrimmed,
            'auditor_required_fixes' => array_slice(
                is_array($auditor['required_fixes'] ?? null) ? $auditor['required_fixes'] : [],
                0,
                $maxFixes,
            ),
            'verdict_trail' => is_array($auditor['verdict_trail'] ?? null) ? $auditor['verdict_trail'] : [],
            'auditor_memory_conflicts' => is_array($auditor['memory_conflicts'] ?? null) ? $auditor['memory_conflicts'] : [],
            'security_audit' => $securityAudit,
            'patch_summary' => $executorResult['patch_summary'] ?? '',
            'tests_result' => $executorResult['tests_result'] ?? 'not_run',
            'files_changed' => array_slice(array_map(
                static fn ($f) => is_array($f) ? ['path' => $f['path'] ?? '', 'summary' => $f['summary'] ?? ''] : (string) $f,
                $files,
            ), 0, $maxFiles),
            'files_changed_omitted' => max(0, count($files) - $maxFiles),
            'known_issues' => is_array($executorResult['known_issues'] ?? null) ? $executorResult['known_issues'] : [],
            'executor_memory_lessons_applied' => $executorResult['memory_lessons_applied'] ?? [],
        ];
    }

    /**
     * Stable sort findings by severity (critical first), preserving original order on ties.
     *
     * @param  list<mixed>  $findings
     * @return list<mixed>
     */
    private function rankBySeverity(array $findings): array
    {
        $indexed = [];
        foreach (array_values($findings) as $i => $finding) {
            $severity = is_array($finding) ? strtolower((string) ($finding['severity'] ?? 'medium')) : 'medium';
            $indexed[] = ['f' => $finding, 'i' => $i, 'rank' => self::SEVERITY_RANK[$severity] ?? 2];
        }
        usort($indexed, static fn (array $a, array $b): int => $a['rank'] <=> $b['rank'] ?: $a['i'] <=> $b['i']);

        return array_map(static fn (array $r) => $r['f'], $indexed);
    }
}
