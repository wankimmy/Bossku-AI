<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_cli_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->constrained('bossku_ai_runs')->cascadeOnDelete();
            $table->string('provider'); // claude|codex|cursor|gemini|...
            $table->string('status')->default('pending'); // pending|running|completed|failed
            $table->string('external_session_id')->nullable();
            $table->string('command')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['run_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_cli_sessions');
    }
};
