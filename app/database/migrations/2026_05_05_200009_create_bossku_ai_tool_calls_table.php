<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_tool_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->nullable()->constrained('bossku_ai_runs')->nullOnDelete();
            $table->foreignUuid('run_step_id')->nullable()->constrained('bossku_ai_run_steps')->nullOnDelete();
            $table->string('tool');
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->string('status')->default('ok');
            $table->text('error')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_tool_calls');
    }
};
