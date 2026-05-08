<?php

namespace App\Providers;

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
use App\Services\Llm\OllamaClient;
use App\Services\Orchestrator\AuditorService;
use App\Services\Orchestrator\DirectAnswerService;
use App\Services\Orchestrator\ExecutorService;
use App\Services\Orchestrator\FinalReviewerService;
use App\Services\Orchestrator\OrchestratorService;
use App\Services\Orchestrator\PlannerService;
use App\Services\Orchestrator\SecurityAuditorService;
use App\Services\Orchestrator\WriterService;
use App\Services\Tools\ToolRegistry;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RuntimeSettings::class);

        $this->app->singleton(OllamaClient::class, function ($app) {
            $s = $app->make(RuntimeSettings::class);
            $key = config('bossku.ollama_api_key');
            $apiKey = is_string($key) && $key !== '' ? $key : null;

            return new OllamaClient($s->ollamaBaseUrl(), $apiKey);
        });

        $this->app->singleton(LlmGateway::class);
        $this->app->singleton(ModelRoutingConfig::class);
        $this->app->singleton(RiskRuleEngine::class);
        $this->app->singleton(DeterministicTaskClassifier::class);
        $this->app->singleton(ModelFallbackService::class);
        $this->app->singleton(ContextBudgetGuard::class);
        $this->app->singleton(PromptRouteClassifier::class);

        $this->app->singleton(MemoryService::class);
        $this->app->singleton(SkillRouterService::class);
        $this->app->singleton(PlannerService::class);
        $this->app->singleton(ExecutorService::class);
        $this->app->singleton(AuditorService::class);
        $this->app->singleton(SecurityAuditorService::class);
        $this->app->singleton(FinalReviewerService::class);
        $this->app->singleton(DirectAnswerService::class);
        $this->app->singleton(WriterService::class);
        $this->app->singleton(ToolRegistry::class);
        $this->app->singleton(OrchestratorService::class);

        $this->app->bind(KnowledgeImportService::class, function ($app) {
            $repo = (string) config('bossku.repo_root');

            return new KnowledgeImportService($repo);
        });
    }

    public function boot(): void {}
}
