<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_learning_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->nullable()->constrained('bossku_ai_runs')->nullOnDelete();
            $table->string('type'); // preference|convention|pattern|correction|escalation
            $table->text('content');
            $table->string('status')->default('pending'); // pending|accepted|rejected|applied
            $table->float('confidence')->default(0.5);
            $table->json('evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('run_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_learning_events');
    }
};
