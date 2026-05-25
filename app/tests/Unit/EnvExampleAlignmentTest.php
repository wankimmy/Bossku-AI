<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnvExampleAlignmentTest extends TestCase
{
    #[Test]
    public function env_example_contains_the_non_secret_runtime_keys_the_app_uses(): void
    {
        $content = file_get_contents(base_path('.env.example'));
        $this->assertIsString($content);

        foreach ([
            'BOSSKU_API_AUTH_ENABLED',
            'BOSSKU_API_TOKEN',
            'BOSSKU_AUTO_APPLY_FILE_WRITES',
            'BOSSKU_ORCHESTRATOR_CLARIFICATION_MODE',
            'BOSSKU_WORKSPACE_MOUNT',
            'BOSSKU_WORKSPACE_HOST_PREFIX',
            'BOSSKU_WORKSPACE_WRITABLE',
            'LOG_IGNORE_EXCEPTIONS',
            'CODEX_OAUTH_CLIENT_ID',
            'CODEX_OAUTH_REDIRECT_URI',
            'CODEX_OAUTH_FRONTEND_RETURN_URL',
        ] as $key) {
            $this->assertMatchesRegularExpression(
                '/^'.preg_quote($key, '/').'=/m',
                $content,
                "Missing {$key} from .env.example",
            );
        }

        foreach ([
            'BOSSKU_ROUTER_LLM_ENABLED',
            'BOSSKU_ROUTER_MODEL',
            'BOSSKU_ORCHESTRATOR_MODEL',
            'BOSSKU_EXECUTOR_DEFAULT_MODEL',
            'BOSSKU_EXECUTOR_FRONTEND_MODEL',
            'BOSSKU_EXECUTOR_BACKEND_MODEL',
            'BOSSKU_EXECUTOR_DEVOPS_MODEL',
            'BOSSKU_EXECUTOR_HIGH_RISK_MODEL',
            'BOSSKU_AUDITOR_MODEL',
            'BOSSKU_SECURITY_AUDITOR_MODEL',
            'BOSSKU_FINAL_REVIEWER_MODEL',
            'BOSSKU_WRITER_MODEL',
            'BOSSKU_DIRECT_ANSWER_MODEL',
            'BOSSKU_OLLAMA_MODEL_PATTERNS',
            'PLANNER_PROVIDER',
            'PLANNER_MODEL',
            'AUDITOR_PROVIDER',
            'AUDITOR_MODEL',
            'EMBEDDING_MODEL',
        ] as $key) {
            $this->assertDoesNotMatchRegularExpression(
                '/^'.preg_quote($key, '/').'=/m',
                $content,
                "Model routing key {$key} should not live in .env.example; manage it from the UI settings.",
            );
        }
    }
}
