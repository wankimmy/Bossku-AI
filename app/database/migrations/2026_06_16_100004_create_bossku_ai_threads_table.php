<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A thread groups a sequence of runs that share conversational/checkpoint
     * lineage (LangGraph threads). Runs reference a thread via runs.thread_id.
     */
    public function up(): void
    {
        Schema::create('bossku_ai_threads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_id')->nullable()
                ->constrained('bossku_ai_assistants')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('status')->default('active'); // active|archived
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['assistant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_threads');
    }
};
