<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_agent_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->constrained('bossku_ai_runs')->cascadeOnDelete();
            $table->foreignUuid('run_step_id')->nullable()->constrained('bossku_ai_run_steps')->nullOnDelete();
            $table->string('agent')->nullable();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('role')->nullable();
            $table->string('skill')->nullable();
            $table->boolean('memory_used')->default(false);
            $table->text('safe_reasoning_summary')->nullable();
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('run_id');
            $table->index('run_step_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_agent_messages');
    }
};
