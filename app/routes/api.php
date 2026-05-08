<?php

use App\Http\Controllers\Api\ChecklistController;
use App\Http\Controllers\Api\KnowledgeImportApiController;
use App\Http\Controllers\Api\MemoryApiController;
use App\Http\Controllers\Api\PlaybookController;
use App\Http\Controllers\Api\RunController;
use App\Http\Controllers\Api\RuleController;
use App\Http\Controllers\Api\SettingsApiController;
use App\Http\Controllers\Api\SkillController;
use Illuminate\Support\Facades\Route;

Route::get('/runs/stream', [RunController::class, 'stream']);
Route::post('/runs', [RunController::class, 'store']);
Route::get('/runs', [RunController::class, 'index']);
Route::get('/runs/{id}', [RunController::class, 'show']);

Route::get('/skills', [SkillController::class, 'index']);
Route::get('/skills/{id}', [SkillController::class, 'show']);
Route::patch('/skills/{id}', [SkillController::class, 'update']);

Route::get('/rules', [RuleController::class, 'index']);
Route::patch('/rules/{id}', [RuleController::class, 'update']);

Route::get('/playbooks', [PlaybookController::class, 'index']);
Route::get('/playbooks/{id}', [PlaybookController::class, 'show']);

Route::get('/checklists', [ChecklistController::class, 'index']);
Route::get('/checklists/{id}', [ChecklistController::class, 'show']);

Route::get('/memory', [MemoryApiController::class, 'index']);
Route::post('/memory/search', [MemoryApiController::class, 'search']);
Route::patch('/memory/{id}', [MemoryApiController::class, 'update']);
Route::delete('/memory/{id}', [MemoryApiController::class, 'destroy']);

Route::get('/settings', [SettingsApiController::class, 'show']);
Route::put('/settings', [SettingsApiController::class, 'update']);

Route::post('/knowledge/import', KnowledgeImportApiController::class);
