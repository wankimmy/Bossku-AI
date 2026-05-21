<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_feedback_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('target_type'); // run|run_step|agent_message|skill|memory|model_route|file_change
            $table->uuid('target_id');
            $table->string('signal'); // thumbs_up|thumbs_down|flag|comment|rating
            $table->tinyInteger('rating')->nullable(); // 1-5
            $table->text('comment')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->index('signal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_feedback_items');
    }
};
