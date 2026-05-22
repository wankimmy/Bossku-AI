<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AgentPersonaController;
use App\Http\Controllers\Api\DataExplorerController;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\BrainController;
use App\Http\Controllers\Api\ChecklistController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\KnowledgeGraphController;
use App\Http\Controllers\Api\KnowledgeImportApiController;
use App\Http\Controllers\Api\LearningController;
use App\Http\Controllers\Api\LogsController;
use App\Http\Controllers\Api\MemoryApiController;
use App\Http\Controllers\Api\ModelRoutingController;
use App\Http\Controllers\Api\OllamaHealthController;
use App\Http\Controllers\Api\PlaybookController;
use App\Http\Controllers\Api\ProjectFilesController;
use App\Http\Controllers\Api\ProjectRegistryController;
use App\Http\Controllers\Api\PluginController;
use App\Http\Controllers\Api\ProviderController;
use App\Http\Controllers\Api\RunActionController;
use App\Http\Controllers\Api\RunController;
use App\Http\Controllers\Api\RuleController;
use App\Http\Controllers\Api\CodexOAuthController;
use App\Http\Controllers\Api\InferenceCatalogController;
use App\Http\Controllers\Api\SettingsApiController;
use App\Http\Controllers\Api\SkillCandidateController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\SkillsGraphController;
use App\Http\Controllers\Api\...

// Public health check
Route::get('/health', [OllamaHealthController::class, 'check']);

// Public OAuth callback (no auth)
Route::get('/auth/codex/callback', [CodexOAuthController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {
    // Agent & Persona
    Route::apiResource('agents', AgentController::class);
    Route::apiResource('agent-personas', AgentPersonaController::class);

    // Data Explorer
    Route::get('data-explorer/tables', [DataExplorerController::class, 'tables']);
    Route::get('data-explorer/table/{table}', [DataExplorerController::class, 'table']);

    // Approvals
    Route::get('approvals', [ApprovalController::class, 'index']);
    Route::post('approvals/{id}/approve', [ApprovalController::class, 'approve']);
    Route::post('approvals/{id}/reject', [ApprovalController::class, 'reject']);

    // Brain
    Route::post('brain/query', [BrainController::class, 'query']);

    // Checklists
    Route::apiResource('checklists', ChecklistController::class);

    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);

    // Feedback
    Route::post('feedback', [FeedbackController::class, 'store']);

    // Knowledge Graph
    Route::get('knowledge-graph', [KnowledgeGraphController::class, 'index']);
    Route::post('knowledge-graph/import', [KnowledgeImportApiController::class, 'import']);

    // Learning
    Route::get('learning/insights', [LearningController::class, 'insights']);
    Route::post('learning/feedback', [LearningController::class, 'feedback']);

    // Logs
    Route::get('logs', [LogsController::class, 'index']);

    // Memory
    Route::get('memory', [MemoryApiController::class, 'index']);
    Route::post('memory', [MemoryApiController::class, 'store']);
    Route::delete('memory/{id}', [MemoryApiController::class, 'destroy']);

    // Model Routing
    Route::get('model-routing', [ModelRoutingController::class, 'index']);
    Route::post('model-routing', [ModelRoutingController::class, 'store']);

    // Playbooks
    Route::apiResource('playbooks', PlaybookController::class);

    // Project Files
    Route::get('project-files', [ProjectFilesController::class, 'index']);
    Route::post('project-files', [ProjectFilesController::class, 'store']);

    // Project Registry
    Route::get('project-registry', [ProjectRegistryController::class, 'index']);
    Route::post('project-registry', [ProjectRegistryController::class, 'store']);

    // Plugins
    Route::apiResource('plugins', PluginController::class);

    // Providers
    Route::apiResource('providers', ProviderController::class);

    // Runs
    Route::get('runs', [RunController::class, 'index']);
    Route::post('runs', [RunController::class, 'store']);
    Route::get('runs/{run}', [RunController::class, 'show']);
    Route::post('runs/{run}/actions', [RunActionController::class, 'store']);

    // Rules
    Route::apiResource('rules', RuleController::class);

    // Inference Catalog
    Route::get('inference-catalog', [InferenceCatalogController::class, 'index']);

    // Settings
    Route::get('settings', [SettingsApiController::class, 'index']);
    Route::put('settings', [SettingsApiController::class, 'update']);

    // Skills
    Route::apiResource('skills', SkillController::class);
    Route::get('skills-graph', [SkillsGraphController::class, 'index']);
    Route::post('skill-candidates', [SkillCandidateController::class, 'store']);
});