<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A scheduled assistant run (LangGraph crons). The scheduler dispatches the
     * assistant with `prompt`/`payload` when the cron expression is due.
     */
    public function up(): void
    {
        Schema::create('bossku_ai_cron_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_id')
                ->constrained('bossku_ai_assistants')->cascadeOnDelete();
            $table->string('name');
            $table->string('cron_expression');
            $table->text('prompt')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_cron_jobs');
    }
};
