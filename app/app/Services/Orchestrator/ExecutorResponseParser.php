<?php

namespace App\Services\Orchestrator;

use App\Support\StringCoercion;

/**
 * Normalize and validate executor LLM JSON before strict pipeline use.
 */
final class ExecutorResponseParser
{
    /**
     * Relaxed gate for ModelFallbackService (before normalization).
     *
     * @param  array<string, mixed>  $decoded
     */
    public static function validateForFallback(array $decoded): bool
    {
        if ($decoded === []) {
            return false;
        }

        if (isset($decoded['status']) || isset($decoded['patch_summary']) || isset($decoded['summary'])) {
            return true;
        }

        return isset($decoded['message']) && StringCoercion::toString($decoded['message']) !== '';
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    public static function normalize(array $decoded): array
    {
        $patch = StringCoercion::toString(
            $decoded['patch_summary'] ?? $decoded['summary'] ?? $decoded['message'] ?? null,
            '',
        );

        $status = StringCoercion::toString($decoded['status'] ?? null, '');
        if ($status === '') {
            $status = $patch !== '' ? 'success' : 'partial';
        }

        return array_merge($decoded, [
            'status' => $status,
            'patch_summary' => $patch,
            'handoff_message' => StringCoercion::toString(
                $decoded['handoff_message'] ?? null,
                'Sending changes to Auditor.',
            ),
            'files_read' => is_array($decoded['files_read'] ?? null) ? $decoded['files_read'] : [],
            'files_changed' => is_array($decoded['files_changed'] ?? null) ? $decoded['files_changed'] : [],
            'commands_run' => is_array($decoded['commands_run'] ?? null) ? $decoded['commands_run'] : [],
            'tests_run' => is_array($decoded['tests_run'] ?? null) ? $decoded['tests_run'] : [],
            'tests_result' => StringCoercion::toString($decoded['tests_result'] ?? null, 'not_run'),
            'known_issues' => is_array($decoded['known_issues'] ?? null) ? $decoded['known_issues'] : [],
            'blockers' => is_array($decoded['blockers'] ?? null) ? $decoded['blockers'] : [],
            'suggested_options' => is_array($decoded['suggested_options'] ?? null) ? $decoded['suggested_options'] : [],
            'needs_user_input' => (bool) ($decoded['needs_user_input'] ?? false),
            'questions' => is_array($decoded['questions'] ?? null) ? $decoded['questions'] : [],
            'needs_audit' => (bool) ($decoded['needs_audit'] ?? true),
        ]);
    }
}
