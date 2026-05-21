<?php

namespace Database\Seeders;

use App\Models\BosskuAi\Approval;
use App\Models\BosskuAi\FeedbackItem;
use App\Models\BosskuAi\GraphEdge;
use App\Models\BosskuAi\GraphNode;
use App\Models\BosskuAi\LearningEvent;
use App\Models\BosskuAi\LlmProvider;
use App\Models\BosskuAi\ModelRoute;
use App\Models\BosskuAi\Plugin;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunStep;
use App\Models\BosskuAi\Skill;
use App\Models\BosskuAi\SkillCandidate;
use App\Models\BosskuAi\SoulVersion;
use App\Models\BosskuAi\UsageEvent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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
        $soul = SoulVersion::create([
            'version' => 'v1.0.0',
            'content' => $soulContent,
            'active' => true,
            'change_summary' => 'Initial soul from spec seeder',
        ]);

        // ── LLM Providers ─────────────────────────────────────────────────────
        $ollamaProvider = LlmProvider::firstOrCreate(
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

        $anthropicProvider = LlmProvider::firstOrCreate(
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

        // ── Model routes ──────────────────────────────────────────────────────
        foreach ([
            ['role' => 'planner', 'model' => 'qwen2.5-coder:7b'],
            ['role' => 'coder', 'model' => 'qwen2.5-coder:7b'],
            ['role' => 'reviewer', 'model' => 'llama3.2'],
            ['role' => 'auditor', 'model' => 'llama3.2'],
            ['role' => 'memory-curator', 'model' => 'llama3.2'],
            ['role' => 'researcher', 'model' => 'llama3.2'],
        ] as $route) {
            ModelRoute::firstOrCreate(
                ['role' => $route['role']],
                [
                    'primary_provider_id' => $ollamaProvider->id,
                    'primary_model' => $route['model'],
                    'is_active' => true,
                ]
            );
        }

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
                'source_run_count' => 4,
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
                'source_run_count' => 3,
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
                'source_run_count' => 3,
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

        // ── Runs + steps ──────────────────────────────────────────────────────
        $runScenarios = [
            ['prompt' => 'Add pagination to the users list API endpoint', 'status' => 'completed', 'skill' => 'laravel-api', 'risk' => 'low', 'audit' => 0.88],
            ['prompt' => 'Create a TypeScript type for the RunStep API response', 'status' => 'completed', 'skill' => 'nuxt-component', 'risk' => 'low', 'audit' => 0.92],
            ['prompt' => 'Add an index on bossku_ai_runs.status column', 'status' => 'completed', 'skill' => 'database-migration', 'risk' => 'medium', 'audit' => 0.95],
            ['prompt' => 'Review the LlmGateway for security issues', 'status' => 'completed', 'skill' => 'code-review', 'risk' => 'high', 'audit' => 0.79],
            ['prompt' => 'Debug the 500 error on /api/skills endpoint', 'status' => 'failed', 'skill' => 'debug-php', 'risk' => 'low', 'audit' => 0.45],
        ];

        $runModels = [];
        foreach ($runScenarios as $scenario) {
            $run = Run::create([
                'prompt' => $scenario['prompt'],
                'status' => $scenario['status'],
                'final_output' => $scenario['status'] === 'completed' ? "Task completed successfully.\n\nImplemented the requested changes following project conventions." : null,
                'total_latency_ms' => rand(2000, 15000),
                'total_token_estimate' => rand(800, 4000),
                'metadata' => ['tags' => [$scenario['skill']]],
                'audit_score' => $scenario['audit'],
                'risk_level' => $scenario['risk'],
                'soul_version_id' => $soul->id,
                'estimated_cost' => round(rand(1, 50) / 1000, 6),
                'selected_skill_name' => $scenario['skill'],
            ]);

            // Create run steps
            $stepTypes = ['memory_retrieval', 'skill_router', 'planner', 'executor', 'auditor', 'final'];
            foreach ($stepTypes as $i => $type) {
                RunStep::create([
                    'run_id' => $run->id,
                    'step_number' => $i + 1,
                    'type' => $type,
                    'model' => 'qwen2.5-coder:7b',
                    'provider' => 'ollama',
                    'skill_name' => $scenario['skill'],
                    'status' => $scenario['status'] === 'failed' && $type === 'executor' ? 'failed' : 'completed',
                    'latency_ms' => rand(200, 3000),
                    'token_estimate' => rand(100, 600),
                    'safe_reasoning_summary' => "Analysed the request and determined the best approach for {$type} phase.",
                    'cost' => round(rand(1, 10) / 10000, 8),
                ]);
            }

            // Usage events
            UsageEvent::create([
                'run_id' => $run->id,
                'provider' => 'ollama',
                'model' => 'qwen2.5-coder:7b',
                'role' => 'coder',
                'input_tokens' => rand(200, 1500),
                'output_tokens' => rand(100, 800),
                'cost_usd' => 0,
                'call_type' => 'chat',
            ]);

            $runModels[] = $run;
        }

        // ── Feedback items ────────────────────────────────────────────────────
        FeedbackItem::create([
            'target_type' => 'run',
            'target_id' => $runModels[0]->id,
            'signal' => 'thumbs_up',
            'comment' => 'Great implementation, exactly what I needed.',
            'processed' => true,
        ]);

        FeedbackItem::create([
            'target_type' => 'skill',
            'target_id' => $skills[0]->id,
            'signal' => 'thumbs_up',
            'comment' => 'This skill works reliably.',
            'processed' => false,
        ]);

        FeedbackItem::create([
            'target_type' => 'run',
            'target_id' => $runModels[4]->id,
            'signal' => 'thumbs_down',
            'comment' => 'Failed to debug the issue correctly.',
            'processed' => false,
        ]);

        // ── Learning events ───────────────────────────────────────────────────
        LearningEvent::create([
            'run_id' => $runModels[0]->id,
            'type' => 'preference',
            'content' => 'User prefers pagination using cursor-based approach over offset-based.',
            'status' => 'pending',
            'confidence' => 0.82,
            'evidence' => ['run_id' => $runModels[0]->id, 'feedback_signal' => 'thumbs_up'],
        ]);

        LearningEvent::create([
            'run_id' => $runModels[2]->id,
            'type' => 'convention',
            'content' => 'Database migrations always use timestamped prefixes in format YYYY_MM_DD_HHMMSS.',
            'status' => 'accepted',
            'confidence' => 0.95,
            'evidence' => ['source' => 'pattern_detection', 'occurrences' => 5],
        ]);

        LearningEvent::create([
            'run_id' => $runModels[3]->id,
            'type' => 'pattern',
            'content' => 'High-risk operations (auth, payment) should be escalated to Opus model.',
            'status' => 'pending',
            'confidence' => 0.75,
            'evidence' => ['source' => 'risk_classifier', 'risk_level' => 'high'],
        ]);

        // ── Approval example ──────────────────────────────────────────────────
        Approval::create([
            'run_id' => $runModels[3]->id,
            'operation_type' => 'external_http',
            'operation_description' => 'Fetch CVE database to check for known vulnerabilities in dependencies.',
            'risk_level' => 'medium',
            'evidence' => ['url' => 'https://nvd.nist.gov/vuln/search', 'purpose' => 'security_audit'],
            'status' => 'approved',
            'decision_note' => 'Approved — read-only security lookup.',
            'decided_by' => 'developer',
            'decided_at' => now(),
        ]);

        // ── Knowledge graph nodes + edges ─────────────────────────────────────
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

        $runNodes = [];
        foreach ($runModels as $run) {
            $runNodes[] = GraphNode::create([
                'type' => 'run',
                'label' => Str::limit($run->prompt, 40),
                'source_id' => $run->id,
                'source_type' => 'run',
                'confidence' => 1.0,
                'has_conflict' => false,
                'properties' => ['status' => $run->status, 'risk_level' => $run->risk_level],
            ]);
        }

        // Conflict node (weak skill)
        $conflictNode = GraphNode::create([
            'type' => 'skill',
            'label' => 'debug-php (weak)',
            'source_id' => $skills[4]->id,
            'source_type' => 'skill',
            'confidence' => 0.55,
            'has_conflict' => true,
            'properties' => ['quality_score' => 55.0, 'reason' => 'low_quality_score'],
        ]);

        // Edges: runs used skills
        foreach ($runNodes as $i => $runNode) {
            $skillName = $runScenarios[$i]['skill'];
            if (isset($skillNodes[$skillName])) {
                GraphEdge::create([
                    'source_node_id' => $runNode->id,
                    'target_node_id' => $skillNodes[$skillName]->id,
                    'relation' => 'used_in',
                    'weight' => 1.0,
                    'is_conflict' => false,
                ]);
            }
        }

        // Conflict edge (duplicate/weak)
        GraphEdge::create([
            'source_node_id' => $skillNodes['debug-php']->id,
            'target_node_id' => $conflictNode->id,
            'relation' => 'conflicts_with',
            'weight' => 0.8,
            'is_conflict' => true,
        ]);

        $this->command?->info('BosskuAI spec seeder: created soul, providers, routes, 5 skills, 3 candidates, 7 plugins, 5 runs, feedback, learning events, approvals, graph nodes/edges.');
    }
}
