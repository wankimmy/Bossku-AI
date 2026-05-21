<?php

use App\Http\Controllers\Api\AgentController;
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
use App\Http\Controllers\Api\PlaybookController;
use App\Http\Controllers\Api\PluginController;
use App\Http\Controllers\Api\ProviderController;
use App\Http\Controllers\Api\RunActionController;
use App\Http\Controllers\Api\RunController;
use App\Http\Controllers\Api\RuleController;
use App\Http\Controllers\Api\SettingsApiController;
use App\Http\Controllers\Api\SkillCandidateController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\SkillsGraphController;
use App\Http\Controllers\Api\SoulController;
use App\Http\Controllers\Api\UsageController;
use Illuminate\Support\Facades\Route;

// ── Runs ─────────────────────────────────────────────────────────────────────
Route::get('/runs/stream', [RunController::class, 'stream']);
Route::post('/runs', [RunController::class, 'store']);
Route::get('/runs', [RunController::class, 'index']);
Route::get('/runs/{id}', [RunController::class, 'show']);

// Run sub-resources
Route::get('/runs/{id}/timeline', [RunController::class, 'timeline']);
Route::get('/runs/{id}/messages', [RunController::class, 'messages']);
Route::get('/runs/{id}/tool-calls', [RunController::class, 'toolCalls']);
Route::get('/runs/{id}/file-changes', [RunController::class, 'fileChanges']);
Route::get('/runs/{id}/audit', [RunController::class, 'auditData']);
Route::get('/runs/{id}/usage', [RunController::class, 'usageData']);
Route::get('/runs/{id}/feedback', [RunController::class, 'feedbackData']);

// Run actions
Route::post('/runs/{runId}/pause', [RunActionController::class, 'pause']);
Route::post('/runs/{runId}/resume', [RunActionController::class, 'resume']);
Route::post('/runs/{runId}/steps/{stepId}/rerun', [RunActionController::class, 'rerunStep']);
Route::post('/runs/{runId}/create-skill', [RunActionController::class, 'createSkill']);

// ── Skills ────────────────────────────────────────────────────────────────────
Route::get('/skills', [SkillController::class, 'index']);
Route::get('/skills/{id}', [SkillController::class, 'show']);
Route::patch('/skills/{id}', [SkillController::class, 'update']);

// Skill candidates
Route::get('/skill-candidates', [SkillCandidateController::class, 'index']);
Route::get('/skill-candidates/{id}', [SkillCandidateController::class, 'show']);
Route::patch('/skill-candidates/{id}', [SkillCandidateController::class, 'update']);
Route::post('/skill-candidates/{id}/approve', [SkillCandidateController::class, 'approve']);
Route::post('/skill-candidates/{id}/reject', [SkillCandidateController::class, 'reject']);

// ── Rules / Playbooks / Checklists ────────────────────────────────────────────
Route::get('/rules', [RuleController::class, 'index']);
Route::patch('/rules/{id}', [RuleController::class, 'update']);

Route::get('/playbooks', [PlaybookController::class, 'index']);
Route::get('/playbooks/{id}', [PlaybookController::class, 'show']);

Route::get('/checklists', [ChecklistController::class, 'index']);
Route::get('/checklists/{id}', [ChecklistController::class, 'show']);

// ── Memory ────────────────────────────────────────────────────────────────────
Route::get('/memory', [MemoryApiController::class, 'index']);
Route::post('/memory/search', [MemoryApiController::class, 'search']);
Route::patch('/memory/{id}', [MemoryApiController::class, 'update']);
Route::delete('/memory/{id}', [MemoryApiController::class, 'destroy']);

// ── Settings ──────────────────────────────────────────────────────────────────
Route::get('/settings', [SettingsApiController::class, 'show']);
Route::put('/settings', [SettingsApiController::class, 'update']);

// ── Knowledge import ──────────────────────────────────────────────────────────
Route::post('/knowledge/import', KnowledgeImportApiController::class);

// ── Dashboard ─────────────────────────────────────────────────────────────────
Route::get('/dashboard', [DashboardController::class, 'index']);

// ── Agents ────────────────────────────────────────────────────────────────────
Route::get('/agents', [AgentController::class, 'index']);
Route::get('/agents/{role}', [AgentController::class, 'show']);

// ── Plugins ───────────────────────────────────────────────────────────────────
Route::get('/plugins', [PluginController::class, 'index']);
Route::post('/plugins', [PluginController::class, 'store']);
Route::get('/plugins/{id}', [PluginController::class, 'show']);
Route::patch('/plugins/{id}', [PluginController::class, 'update']);
Route::delete('/plugins/{id}', [PluginController::class, 'destroy']);
Route::post('/plugins/{id}/heartbeat', [PluginController::class, 'heartbeat']);

// ── Feedback ──────────────────────────────────────────────────────────────────
Route::get('/feedback', [FeedbackController::class, 'index']);
Route::post('/feedback', [FeedbackController::class, 'store']);
Route::get('/feedback/{targetType}/{targetId}/summary', [FeedbackController::class, 'summary']);

// ── Learning ──────────────────────────────────────────────────────────────────
Route::get('/learning', [LearningController::class, 'index']);
Route::post('/learning/{id}/accept', [LearningController::class, 'accept']);
Route::post('/learning/{id}/reject', [LearningController::class, 'reject']);

// ── Brain ─────────────────────────────────────────────────────────────────────
Route::get('/brain', [BrainController::class, 'index']);

// ── Knowledge graph ───────────────────────────────────────────────────────────
Route::get('/knowledge-graph', [KnowledgeGraphController::class, 'index']);
Route::post('/knowledge-graph/rebuild', [KnowledgeGraphController::class, 'rebuild']);
Route::get('/knowledge-graph/nodes/{id}', [KnowledgeGraphController::class, 'node']);

// ── Skills graph ──────────────────────────────────────────────────────────────
Route::get('/skills-graph', [SkillsGraphController::class, 'index']);
Route::post('/skills-graph/rebuild', [SkillsGraphController::class, 'rebuild']);

// ── Soul ──────────────────────────────────────────────────────────────────────
Route::get('/soul', [SoulController::class, 'show']);
Route::put('/soul', [SoulController::class, 'update']);
Route::get('/soul/history', [SoulController::class, 'history']);
Route::get('/soul/suggestions', [SoulController::class, 'suggestions']);

// ── Usage ─────────────────────────────────────────────────────────────────────
Route::get('/usage', [UsageController::class, 'index']);
Route::get('/usage/summary', [UsageController::class, 'summary']);

// ── Logs ──────────────────────────────────────────────────────────────────────
Route::get('/logs', [LogsController::class, 'index']);

// ── LLM Providers ────────────────────────────────────────────────────────────
Route::get('/providers', [ProviderController::class, 'index']);
Route::post('/providers', [ProviderController::class, 'store']);
Route::get('/providers/{id}', [ProviderController::class, 'show']);
Route::patch('/providers/{id}', [ProviderController::class, 'update']);
Route::delete('/providers/{id}', [ProviderController::class, 'destroy']);
Route::post('/providers/{id}/test', [ProviderController::class, 'testConnection']);
Route::post('/providers/{id}/sync-models', [ProviderController::class, 'syncModels']);

// ── Model routing ────────────────────────────────────────────────────────────
Route::get('/model-routes', [ModelRoutingController::class, 'index']);
Route::post('/model-routes', [ModelRoutingController::class, 'store']);
Route::patch('/model-routes/{id}', [ModelRoutingController::class, 'update']);
Route::delete('/model-routes/{id}', [ModelRoutingController::class, 'destroy']);

// ── Approvals ────────────────────────────────────────────────────────────────
Route::get('/approvals', [ApprovalController::class, 'index']);
Route::get('/approvals/{id}', [ApprovalController::class, 'show']);
Route::post('/approvals/{id}/approve', [ApprovalController::class, 'approve']);
Route::post('/approvals/{id}/reject', [ApprovalController::class, 'reject']);
