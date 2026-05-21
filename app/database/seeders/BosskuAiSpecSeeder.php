<?php

namespace Database\Seeders;

use App\Models\BosskuAi\GraphEdge;
use App\Models\BosskuAi\GraphNode;
use App\Models\BosskuAi\LlmProvider;
use App\Models\BosskuAi\Plugin;
use App\Models\BosskuAi\Skill;
use App\Models\BosskuAi\SkillCandidate;
use App\Models\BosskuAi\SoulVersion;
use Illuminate\Database\Seeder;

class BosskuAiSpecSeeder extends Seeder
{
    public function run(): void
    {
        // ── Soul version ──────────────────────────────────────────────────────
        $soulPath = base_path('bossku/soul.md');
        $soulContent = file_exists($soulPath)
            ? file_get_contents($soulPath)
            : "# BosskuAI Soul v1.0.0\n\n## Identity\nBosskuAI is a self-learning developer AI orchestrator.\n";

        SoulVersion::where('active', true)->update(['active' => false]);
        SoulVersion::create([
            'version' => 'v1.0.0',
            'content' => $soulContent,
            'active' => true,
            'change_summary' => 'Initial soul from spec seeder',
        ]);

        // ── LLM Providers (no demo model routes — configure in Settings) ───────
        LlmProvider::firstOrCreate(
            ['slug' => 'ollama-local'],
            [
                'name' => 'Ollama (Local)',
                'type' => 'ollama',
                'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
                'api_key_env' => 'OLLAMA_API_KEY',
                'is_active' => true,
                'health_status' => 'healthy',
                'available_models' => ['llama3.2', 'qwen2.5-coder:7b', 'glm-5.1:cloud'],
            ]
        );

        LlmProvider::firstOrCreate(
            ['slug' => 'anthropic'],
            [
                'name' => 'Anthropic',
                'type' => 'anthropic',
                'base_url' => 'https://api.anthropic.com',
                'api_key_env' => 'ANTHROPIC_API_KEY',
                'is_active' => false,
                'health_status' => 'unknown',
                'available_models' => ['claude-sonnet-4-6', 'claude-opus-4-7', 'claude-haiku-4-5'],
            ]
        );

        // ── Skills ────────────────────────────────────────────────────────────
        $skillData = [
            [
                'name' => 'laravel-api',
                'description' => 'Build Laravel REST API endpoints with controllers, models, and migrations.',
                'quality_score' => 82.0,
                'feedback_score' => 0.85,
                'approval_status' => 'approved',
                'confidence' => 0.9,
                'usage_count' => 14,
                'version' => '2.1.0',
            ],
            [
                'name' => 'nuxt-component',
                'description' => 'Create Nuxt 3 Vue components with TypeScript and Tailwind CSS.',
                'quality_score' => 76.0,
                'feedback_score' => 0.78,
                'approval_status' => 'approved',
                'confidence' => 0.85,
                'usage_count' => 9,
                'version' => '1.3.0',
            ],
            [
                'name' => 'database-migration',
                'description' => 'Write Laravel database migrations following team conventions.',
                'quality_score' => 90.0,
                'feedback_score' => 0.92,
                'approval_status' => 'approved',
                'confidence' => 0.95,
                'usage_count' => 22,
                'version' => '3.0.0',
            ],
            [
                'name' => 'code-review',
                'description' => 'Review code for quality, security, and performance issues.',
                'quality_score' => 68.0,
                'feedback_score' => 0.71,
                'approval_status' => 'approved',
                'confidence' => 0.75,
                'usage_count' => 7,
                'version' => '1.0.0',
            ],
            [
                'name' => 'debug-php',
                'description' => 'Debug PHP/Laravel errors and exceptions.',
                'quality_score' => 55.0,
                'feedback_score' => 0.60,
                'approval_status' => 'approved',
                'confidence' => 0.70,
                'usage_count' => 3,
                'version' => '1.0.0',
            ],
        ];

        $skills = [];
        foreach ($skillData as $sd) {
            $skills[] = Skill::firstOrCreate(
                ['name' => $sd['name']],
                array_merge($sd, [
                    'content' => "# {$sd['name']}\n\n## Description\n{$sd['description']}\n\n## Rules\n- Follow team conventions\n- Write tests\n",
                    'is_active' => true,
                ])
            );
        }

        // ── Skill candidates ──────────────────────────────────────────────────
        SkillCandidate::firstOrCreate(
            ['name' => 'typescript-types'],
            [
                'description' => 'Generate TypeScript interfaces from API responses.',
                'category' => 'frontend',
                'approval_status' => 'pending_review',
                'quality_score' => 62.0,
                'source_run_count' => 0,
                'tags' => ['typescript', 'frontend', 'types'],
                'draft_content' => "# typescript-types\n\n## Description\nGenerate TypeScript interfaces from API responses.\n\n## Rules\n- Use `interface` for objects\n- Use `type` for unions\n",
            ]
        );

        SkillCandidate::firstOrCreate(
            ['name' => 'eloquent-relationships'],
            [
                'description' => 'Define Eloquent model relationships following project patterns.',
                'category' => 'backend',
                'approval_status' => 'draft',
                'quality_score' => 45.0,
                'source_run_count' => 0,
                'tags' => ['laravel', 'eloquent', 'backend'],
                'draft_content' => "# eloquent-relationships\n\n## Description\nDefine Eloquent model relationships following project patterns.\n",
            ]
        );

        SkillCandidate::firstOrCreate(
            ['name' => 'payment-refund'],
            [
                'description' => 'Process payment refunds via Stripe.',
                'category' => 'payment-gateway',
                'approval_status' => 'pending_review',
                'quality_score' => 35.0,
                'source_run_count' => 0,
                'tags' => ['payment', 'stripe'],
                'draft_content' => "# payment-refund\n\n## Description\nProcess payment refunds via Stripe.\n",
            ]
        );

        // ── Plugins ───────────────────────────────────────────────────────────
        $pluginData = [
            ['name' => 'Git Helper', 'slug' => 'git-helper', 'description' => 'Executes git commands (status, log, diff, commit)', 'version' => '1.2.0', 'author' => 'BosskuAI', 'permissions' => ['git:read', 'git:write'], 'is_active' => true],
            ['name' => 'File Browser', 'slug' => 'file-browser', 'description' => 'Read and write files within the project directory', 'version' => '2.0.1', 'author' => 'BosskuAI', 'permissions' => ['fs:read', 'fs:write'], 'is_active' => true],
            ['name' => 'HTTP Fetcher', 'slug' => 'http-fetcher', 'description' => 'Make outbound HTTP requests', 'version' => '1.0.0', 'author' => 'BosskuAI', 'permissions' => ['http:get', 'http:post'], 'is_active' => false],
            ['name' => 'SQL Runner', 'slug' => 'sql-runner', 'description' => 'Execute read-only SQL queries against the project database', 'version' => '1.1.0', 'author' => 'BosskuAI', 'permissions' => ['db:read'], 'is_active' => true],
            ['name' => 'Docker Compose', 'slug' => 'docker-compose', 'description' => 'Manage Docker Compose services', 'version' => '0.9.0', 'author' => 'BosskuAI', 'permissions' => ['docker:read', 'docker:exec'], 'is_active' => false],
            ['name' => 'NPM Manager', 'slug' => 'npm-manager', 'description' => 'Run npm install and audit commands', 'version' => '1.0.0', 'author' => 'BosskuAI', 'permissions' => ['npm:install', 'npm:audit'], 'is_active' => true],
            ['name' => 'Test Runner', 'slug' => 'test-runner', 'description' => 'Run PHPUnit and vitest test suites', 'version' => '1.3.0', 'author' => 'BosskuAI', 'permissions' => ['test:run'], 'is_active' => true],
        ];

        foreach ($pluginData as $pd) {
            Plugin::firstOrCreate(['slug' => $pd['slug']], $pd);
        }

        // ── Knowledge graph (skills only — runs/feedback come from real usage) ─
        $skillNodes = [];
        foreach ($skills as $skill) {
            $skillNodes[$skill->name] = GraphNode::create([
                'type' => 'skill',
                'label' => $skill->name,
                'source_id' => $skill->id,
                'source_type' => 'skill',
                'confidence' => $skill->confidence ?? 1.0,
                'has_conflict' => false,
                'properties' => [
                    'quality_score' => $skill->quality_score,
                    'usage_count' => $skill->usage_count,
                ],
            ]);
        }

        $conflictNode = GraphNode::create([
            'type' => 'skill',
            'label' => 'debug-php (weak)',
            'source_id' => $skills[4]->id,
            'source_type' => 'skill',
            'confidence' => 0.55,
            'has_conflict' => true,
            'properties' => ['quality_score' => 55.0, 'reason' => 'low_quality_score'],
        ]);

        GraphEdge::create([
            'source_node_id' => $skillNodes['debug-php']->id,
            'target_node_id' => $conflictNode->id,
            'relation' => 'conflicts_with',
            'weight' => 0.8,
            'is_conflict' => true,
        ]);

        $this->command?->info('BosskuAI spec seeder: soul, providers, 5 skills, 3 candidates, 7 plugins, skill graph nodes (no demo runs, feedback, or model routes).');
    }
}
