<?php

namespace App\Services\Recipes;

/**
 * Lightweight prompt-injection / destructive-pattern scanner for recipes
 * (goose recipe-scanner concept). Shared recipes are untrusted input: scan the
 * rendered prompt + instructions before running so a malicious recipe can't
 * hijack the agent. Heuristic, not a sandbox — pair with approval gates for
 * anything it flags high.
 *
 * @phpstan-type Finding array{severity: string, rule: string, match: string, message: string}
 */
final class RecipeSecurityScanner
{
    /** @var list<array{severity: string, rule: string, pattern: string, message: string}> */
    private const RULES = [
        ['severity' => 'high', 'rule' => 'instruction_override', 'pattern' => '/ignore\s+(all\s+)?(previous|prior|above)\s+(instructions|prompts|rules)/i', 'message' => 'Attempts to override prior instructions (prompt injection).'],
        ['severity' => 'high', 'rule' => 'role_hijack', 'pattern' => '/(disregard|forget)\s+(your|the)\s+(system|safety|guard)/i', 'message' => 'Attempts to disable system/safety guidance.'],
        ['severity' => 'high', 'rule' => 'exfiltration', 'pattern' => '/(exfiltrat|send|post|upload|leak)\b.{0,40}\b(secret|token|api[_\s-]?key|password|credential|\.env|private\s+key)/i', 'message' => 'Attempts to exfiltrate secrets/credentials.'],
        ['severity' => 'high', 'rule' => 'destructive_shell', 'pattern' => '/\brm\s+-rf\s+[\/~]|\bmkfs\b|\bdd\s+if=|:\(\)\s*\{\s*:\|:/i', 'message' => 'Contains a destructive shell command.'],
        ['severity' => 'high', 'rule' => 'remote_exec', 'pattern' => '/(curl|wget)\b[^\n|]*\|\s*(sudo\s+)?(bash|sh|zsh|python\d?)/i', 'message' => 'Pipes a remote download straight into a shell (remote code execution).'],
        ['severity' => 'medium', 'rule' => 'obfuscated_exec', 'pattern' => '/base64\s+(-d|--decode)\b[^\n]*\|\s*(bash|sh|python)/i', 'message' => 'Decodes and executes obfuscated payload.'],
        ['severity' => 'medium', 'rule' => 'credential_read', 'pattern' => '/(cat|less|type)\s+[^\n]*(\.env|id_rsa|\.aws\/credentials|\.ssh\/)/i', 'message' => 'Reads credential/secret files.'],
        ['severity' => 'medium', 'rule' => 'history_rewrite', 'pattern' => '/git\s+push\s+(--force|-f)\b|git\s+reset\s+--hard/i', 'message' => 'Rewrites/force-pushes git history.'],
        ['severity' => 'low', 'rule' => 'tool_disable', 'pattern' => '/(disable|turn\s+off|skip)\s+(the\s+)?(auditor|security|review|approval|guard)/i', 'message' => 'Asks to skip review/approval/security stages.'],
    ];

    /**
     * @return list<Finding>
     */
    public function scanRecipe(Recipe $recipe, array $values = []): array
    {
        $text = $recipe->render($values)."\n".($recipe->instructions ?? '');

        return $this->scanText($text);
    }

    /**
     * @return list<Finding>
     */
    public function scanText(string $text): array
    {
        $findings = [];
        foreach (self::RULES as $rule) {
            if (preg_match($rule['pattern'], $text, $m) === 1) {
                $findings[] = [
                    'severity' => $rule['severity'],
                    'rule' => $rule['rule'],
                    'match' => mb_substr(trim($m[0]), 0, 120),
                    'message' => $rule['message'],
                ];
            }
        }

        return $findings;
    }

    /** @param list<Finding> $findings */
    public function highestSeverity(array $findings): string
    {
        foreach (['high', 'medium', 'low'] as $level) {
            foreach ($findings as $f) {
                if ($f['severity'] === $level) {
                    return $level;
                }
            }
        }

        return 'none';
    }
}
