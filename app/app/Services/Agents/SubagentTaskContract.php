<?php

namespace App\Services\Agents;

/**
 * The structured prompt and output envelope for background subagent tasks.
 * Ported from opencode's task tool (packages/opencode/src/tool/task.ts:31-79).
 *
 * opencode's proven prompt-engineering for background subagents — BosskuAI
 * shouldn't re-derive it. The key insight: when a background task is running,
 * the parent model must NOT sleep, poll, or proactively check on progress.
 * It should continue other work; the result arrives as a synthetic message.
 */
final class SubagentTaskContract
{
    /**
     * The instruction text injected into the parent's context when a
     * background subagent is dispatched. Copied from opencode's proven prompt
     * (task.ts:31-41) — the model reliably respects this wording.
     */
    public const BACKGROUND_INSTRUCTION = <<<'TXT'
You have dispatched a background task. DO NOT sleep, poll, or proactively check on its progress.
Continue with other work; the task result will be delivered to you automatically when it completes.
Do not wait for it. Do not ask about it. Do not duplicate its work.
TXT;

    /**
     * The output envelope a subagent result is wrapped in. Copied from
     * opencode's <task> tag format (task.ts:64-79) — the parent model parses
     * this reliably.
     *
     * @param  string  $taskId
     * @param  string  $state  "running" | "completed" | "error"
     * @param  string  $summary  one-line summary
     * @param  string  $result  the full result text
     * @return string  the formatted envelope
     */
    public static function resultEnvelope(string $taskId, string $state, string $summary, string $result): string
    {
        $summary = trim($summary);
        $result = trim($result);

        return "<task id=\"{$taskId}\" state=\"{$state}\">\n"
            .($summary !== '' ? "<summary>{$summary}</summary>\n" : '')
            ."<task_result>{$result}</task_result>\n"
            ."</task>";
    }

    /**
     * The system prompt fragment that tells the parent model how to handle
     * a completed background task. Injected when the result is delivered.
     */
    public const RESULT_DELIVERY_INSTRUCTION = <<<'TXT'
A background task has completed. Review the <task_result> above, then continue your work using its output.
Do not re-do the task. Do not ask about it. Integrate the result and proceed.
TXT;
}