<?php

namespace App\Services\Orchestrator;

use App\Support\StringCoercion;

/**
 * Merges planner checklist, executor self-report, auditor verdicts, and evidence
 * into authoritative per-item statuses for the UI and run summary.
 */
class ChecklistReconciler
{
    /**
     * @param  list<array<string, mixed>>  $planChecklist
     * @param  list<array<string, mixed>>  $checklistStatus
     * @param  list<array<string, mixed>>  $verdictTrail
     * @param  array<string, mixed>  $evidence
     * @return list<array<string, mixed>>
     */
    public static function reconcile(
        array $planChecklist,
        array $checklistStatus,
        array $verdictTrail,
        array $evidence,
    ): array {
        if ($planChecklist === []) {
            return [];
        }

        $hasEvidence = ($evidence['has_evidence'] ?? false) === true;
        $execById = self::indexById($checklistStatus);
        $verdictById = self::indexVerdictById($verdictTrail);
        $hasAuditorVerdicts = $verdictTrail !== [];

        return array_values(array_map(function (array $item) use (
            $hasEvidence,
            $execById,
            $verdictById,
            $hasAuditorVerdicts,
        ) {
            $id = StringCoercion::toString($item['id'] ?? null);
            $verdict = $id !== '' ? ($verdictById[$id] ?? null) : null;
            $execStatus = $id !== '' ? StringCoercion::toString($execById[$id]['status'] ?? null, '') : '';

            $status = self::resolveItemStatus(
                $verdict,
                $execStatus,
                $hasEvidence,
                $hasAuditorVerdicts,
            );

            return array_merge($item, ['status' => $status]);
        }, $planChecklist));
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @return array{has_evidence: bool, proof_files: list<string>}
     */
    public static function evidenceFromExecutorResult(array $execResult): array
    {
        $proofFiles = ExecutorEvidenceSupport::proofFilePaths($execResult);
        $hasSuccessfulCommand = false;
        $executed = is_array($execResult['_commands_executed'] ?? null) ? $execResult['_commands_executed'] : [];
        foreach ($executed as $row) {
            if (is_array($row) && ($row['ok'] ?? false) === true) {
                $hasSuccessfulCommand = true;
                break;
            }
        }

        return [
            'has_evidence' => $proofFiles !== [] || $hasSuccessfulCommand,
            'proof_files' => $proofFiles,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $checklist
     * @return array{
     *   total: int,
     *   verified: int,
     *   disputed: int,
     *   unverifiable: int,
     *   failed: int,
     *   needs_revision: int,
     *   has_issues: bool
     * }
     */
    public static function summarizeChecklist(array $checklist): array
    {
        $total = count($checklist);
        $verified = 0;
        $disputed = 0;
        $unverifiable = 0;
        $failed = 0;
        $needsRevision = 0;

        foreach ($checklist as $item) {
            $status = StringCoercion::toString($item['status'] ?? null, 'pending');
            match ($status) {
                'completed', 'success', 'passed' => $verified++,
                'disputed' => $disputed++,
                'unverifiable' => $unverifiable++,
                'failed', 'fail' => $failed++,
                'needs_revision' => $needsRevision++,
                default => null,
            };
        }

        $hasIssues = ($disputed + $unverifiable + $failed + $needsRevision) > 0;

        return [
            'total' => $total,
            'verified' => $verified,
            'disputed' => $disputed,
            'unverifiable' => $unverifiable,
            'failed' => $failed,
            'needs_revision' => $needsRevision,
            'has_issues' => $hasIssues,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $checklist
     * @param  list<array<string, mixed>>  $verdictTrail
     */
    public static function formatVerdictSummary(array $checklist, array $verdictTrail): string
    {
        $stats = self::summarizeChecklist($checklist);
        if ($stats['total'] === 0) {
            return 'No checklist items to reconcile.';
        }

        $parts = [
            $stats['verified'].'/'.$stats['total'].' checklist item(s) verified',
        ];
        $nonVerified = $stats['total'] - $stats['verified'];
        if ($nonVerified > 0) {
            $parts[] = $nonVerified.' not fully verified';
        }
        if ($verdictTrail !== []) {
            $disputedInTrail = count(array_filter(
                $verdictTrail,
                static fn ($v) => ($v['auditor_verdict'] ?? '') !== 'verified',
            ));
            if ($disputedInTrail > 0) {
                $parts[] = $disputedInTrail.' disputed or unverifiable in audit trail';
            }
        }

        return implode('; ', $parts).'.';
    }

    /**
     * @param  array<string, mixed>|null  $verdict
     */
    protected static function resolveItemStatus(
        ?array $verdict,
        string $execStatus,
        bool $hasEvidence,
        bool $hasAuditorVerdicts,
    ): string {
        if ($hasAuditorVerdicts) {
            if ($verdict === null) {
                return 'unverifiable';
            }

            $auditorVerdict = StringCoercion::toString($verdict['auditor_verdict'] ?? null, 'unverifiable');

            return match ($auditorVerdict) {
                'disputed' => 'disputed',
                'unverifiable' => 'unverifiable',
                'verified' => $hasEvidence ? 'completed' : 'unverifiable',
                default => 'unverifiable',
            };
        }

        return self::statusFromExecutorReport($execStatus, $hasEvidence);
    }

    protected static function statusFromExecutorReport(string $execStatus, bool $hasEvidence): string
    {
        return match ($execStatus) {
            'completed', 'success', 'passed' => $hasEvidence ? 'completed' : 'unverifiable',
            'partial' => 'needs_revision',
            'failed', 'fail' => 'failed',
            'skipped' => 'skipped',
            'needs_revision' => 'needs_revision',
            default => 'unverifiable',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    protected static function indexById(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = StringCoercion::toString($row['id'] ?? null);
            if ($id === '') {
                continue;
            }
            $indexed[$id] = $row;
        }

        return $indexed;
    }

    /**
     * @param  list<array<string, mixed>>  $verdictTrail
     * @return array<string, array<string, mixed>>
     */
    protected static function indexVerdictById(array $verdictTrail): array
    {
        $indexed = [];
        foreach ($verdictTrail as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = StringCoercion::toString($row['id'] ?? null);
            if ($id === '') {
                continue;
            }
            $indexed[$id] = $row;
        }

        return $indexed;
    }
}
