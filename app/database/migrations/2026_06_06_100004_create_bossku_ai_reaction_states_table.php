<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_reaction_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->constrained('bossku_ai_runs')->cascadeOnDelete();
            $table->string('reaction_key'); // ci_failed|changes_requested|...
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->json('last_payload')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'reaction_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_reaction_states');
    }
};
