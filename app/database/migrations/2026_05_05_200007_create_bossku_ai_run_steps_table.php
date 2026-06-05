<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_run_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->constrained('bossku_ai_runs')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_number')->nullable();
            $table->string('type'); // memory_retrieval, skill_router, planner, executor, auditor, final
            $table->string('model')->nullable();
            $table->string('provider')->nullable();
            $table->string('skill_name')->nullable()->index();
            $table->string('status')->default('pending');
            $table->longText('input')->nullable();
            $table->longText('output')->nullable();
            $table->json('rules_used')->nullable();
            $table->json('playbooks_used')->nullable();
            $table->json('checklists_used')->nullable();
            $table->json('memory_used')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('token_estimate')->nullable();
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_run_steps');
    }
};
