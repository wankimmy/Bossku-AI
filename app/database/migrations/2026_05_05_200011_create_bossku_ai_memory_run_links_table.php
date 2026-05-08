<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_memory_run_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('memory_id')->constrained('bossku_ai_memories')->cascadeOnDelete();
            $table->foreignUuid('run_id')->constrained('bossku_ai_runs')->cascadeOnDelete();
            $table->float('similarity_score')->nullable();
            $table->timestamps();
            $table->unique(['memory_id', 'run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_memory_run_links');
    }
};
