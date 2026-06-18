<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_agent_wakeup_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('specialist_agent_id')->nullable()->constrained('bossku_ai_specialist_agents')->nullOnDelete();
            $table->foreignUuid('work_issue_id')->nullable()->constrained('bossku_ai_work_issues')->nullOnDelete();
            $table->foreignUuid('run_id')->nullable()->constrained('bossku_ai_runs')->nullOnDelete();
            $table->string('wake_reason');
            $table->string('status')->default('queued');
            $table->string('idempotency_key')->nullable();
            $table->json('context_snapshot')->nullable();
            $table->text('skip_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->unique(['specialist_agent_id', 'work_issue_id', 'wake_reason', 'idempotency_key'], 'bossku_wakeup_idempotent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_agent_wakeup_requests');
    }
};
