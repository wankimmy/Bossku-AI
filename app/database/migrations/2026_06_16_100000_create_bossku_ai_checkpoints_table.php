<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_checkpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // thread_id == run id; one thread of checkpoints per run.
            $table->uuid('thread_id');
            $table->uuid('parent_id')->nullable();
            $table->longText('channel_values'); // JSON snapshot of every channel
            $table->json('next');               // frontier node names to run next
            $table->unsignedInteger('step')->default(0);
            $table->string('source')->default('loop'); // input|loop|interrupt|fork
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('thread_id')->references('id')->on('bossku_ai_runs')->cascadeOnDelete();
            $table->index(['thread_id', 'step']);
            $table->index(['thread_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_checkpoints');
    }
};
