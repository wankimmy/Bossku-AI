<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pending per-task channel writes, recorded before a checkpoint is finalized
     * so an interrupted superstep can recover partial progress. Phase 1 lays the
     * table; the runner's fine-grained write-recovery uses it from Phase 3
     * (parallel nodes) onward.
     */
    public function up(): void
    {
        Schema::create('bossku_ai_checkpoint_writes', function (Blueprint $table) {
            $table->uuid('checkpoint_id');
            $table->string('task_id');
            $table->unsignedInteger('idx');
            $table->string('channel');
            $table->longText('value')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['checkpoint_id', 'task_id', 'idx']);
            $table->foreign('checkpoint_id')->references('id')->on('bossku_ai_checkpoints')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_checkpoint_writes');
    }
};
