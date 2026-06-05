<?php

namespace App\Providers;

use App\Services\Attachments\AttachmentIngestionService;
use App\Services\Attachments\AttachmentRunContextService;
use App\Services\Attachments\VisionService;
use App\Services\BosskuAi\AgentPersonaService;
use App\Services\BosskuAi\CodebaseIndexService;
use App\Services\BosskuAi\ContextBudgetGuard;
use App\Services\BosskuAi\DeterministicTaskClassifier;
use App\Services\BosskuAi\KnowledgeImportService;
use App\Services\BosskuAi\LlmGateway;
use App\Services\BosskuAi\MemoryService;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\BosskuAi\PromptRouteClassifier;
use App\Services\BosskuAi\RiskRuleEngine;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\BosskuAi\SkillRouterService;
use App\Services\Llm\ModelRouter;
use App\Services\Llm\OllamaClient;
use App\Services\Llm\Providers\AnthropicProvider;
use App\Services\Llm\Providers\CodexOAuthProvider;
use App\Services\Llm\Providers\OllamaProvider;
use App\Services\Llm\UsageTracker;
use App\Services\Runs\RunExistenceGuard;
use App\Services\OAuth\CodexOAuthService;
use App\Services\Orchestrator\AuditorService;
use App\Services\Orchestrator\DirectAnswerService;
use App\Services\Orchestrator\ExecutorService;
use App\Services\Orchestrator\FinalReviewerService;
use App\Services\Orchestrator\OrchestratorService;
use App\Services\Orchestrator\PlannerService;
use App\Services\Orchestrator\SecurityAuditorService;
use App\Services\Orchestrator\WriterService;
use App\Services\Data\DataExplorerService;
use App\Services\Learning\FeedbackReportService;
use App\Services\Orchestrator\RunSupervisorService;
use App\Services\Orchestrator\SupervisorMergeCoordinator;
use App\Services\BosskuAi\MemoryWorktreeScope;
use App\Services\Project\ProjectCommandRunner;
use App\Services\Project\RunExecutionContext;
use App\Services\Providers\CliSessionService;
use App\Services\Providers\ProviderCliRegistry;
use App\Services\Scm\GithubScmService;
use App\Services\Scm\ReactionEngine;
use App\Services\Tools\ToolRegistry;
use App\Services\Workspace\ByoiWorkspaceProvisioner;
use App\Services\Workspace\WorktreeManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RuntimeSettings::class);
        $this->app->singleton(RunExistenceGuard::class);
        $this->app->singleton(CodexOAuthService::class);

        $this->app->singleton(OllamaClient::class, function ($app) {
            $s = $app->make(RuntimeSettings::class);

            return new OllamaClient($s->ollamaBaseUrl(), $s->ollamaApiKey());
        });

        $this->app->singleton(ModelRouter::class, function ($app) {
            $router = new ModelRouter($app->make(UsageTracker::class));
            $router->registerProvider(new OllamaProvider($app->make(OllamaClient::class)));

            $settings = $app->make(RuntimeSettings::class);
            $anthropicKey = $settings->anthropicApiKey();
            if (is_string($anthropicKey) && $anthropicKey !== '') {
                $router->registerProvider(new AnthropicProvider($anthropicKey));
            }

            $codex = $app->make(CodexOAuthService::class);
            if ($codex->isConnected()) {
                $router->registerProvider(new CodexOAuthProvider(
                    $codex,
                    (string) config('bossku_oauth.codex.api_base_url', 'https://api.openai.com'),
                ));
            }

            return $router;
        });

        $this->app->singleton(LlmGateway::class, function ($app) {
            return new LlmGateway(
                $app->make(OllamaClient::class),
                $app->make(RuntimeSettings::class),
                $app->make(ModelRouter::class),
                $app->make(CodexOAuthService::class),
                $app->make(UsageTracker::class),
            );
        });
        $this->app->singleton(ModelRoutingConfig::class);
        $this->app->singleton(RiskRuleEngine::class);
        $this->app->singleton(DeterministicTaskClassifier::class);
        $this->app->singleton(AgentPersonaService::class);
        $this->app->singleton(DataExplorerService::class);
        $this->app->singleton(ModelFallbackService::class);
        $this->app->singleton(ContextBudgetGuard::class);
        $this->app->singleton(PromptRouteClassifier::class);

        $this->app->singleton(MemoryService::class);
        $this->app->singleton(CodebaseIndexService::class);
        $this->app->singleton(SkillRouterService::class);
        $this->app->singleton(PlannerService::class);
        $this->app->singleton(ExecutorService::class);
        $this->app->singleton(AuditorService::class);
        $this->app->singleton(SecurityAuditorService::class);
        $this->app->singleton(FinalReviewerService::class);
        $this->app->singleton(DirectAnswerService::class);
        $this->app->singleton(WriterService::class);
        $this->app->singleton(ToolRegistry::class);
        $this->app->singleton(RunExecutionContext::class);
        $this->app->singleton(WorktreeManager::class);
        $this->app->singleton(ByoiWorkspaceProvisioner::class);
        $this->app->singleton(MemoryWorktreeScope::class);
        $this->app->singleton(SupervisorMergeCoordinator::class);
        $this->app->singleton(RunSupervisorService::class);
        $this->app->singleton(ProviderCliRegistry::class);
        $this->app->singleton(CliSessionService::class);
        $this->app->singleton(GithubScmService::class);
        $this->app->singleton(ReactionEngine::class);
        $this->app->singleton(FeedbackReportService::class);
        $this->app->singleton(OrchestratorService::class);
        $this->app->singleton(VisionService::class);
        $this->app->singleton(AttachmentIngestionService::class);
        $this->app->singleton(AttachmentRunContextService::class);

        $this->app->bind(KnowledgeImportService::class, function ($app) {
            $repo = (string) config('bossku.repo_root');

            return new KnowledgeImportService($repo, $app->make(MemoryService::class));
        });
    }

    public function boot(): void
    {
        //
    }
}
