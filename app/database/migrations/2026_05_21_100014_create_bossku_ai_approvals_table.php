<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->constrained('bossku_ai_runs')->cascadeOnDelete();
            $table->foreignUuid('run_step_id')->nullable()->constrained('bossku_ai_run_steps')->nullOnDelete();
            $table->string('operation_type'); // terminal_command|external_http|env_mod|deployment|secret_rotation|high_cost
            $table->text('operation_description');
            $table->string('risk_level')->default('medium'); // low|medium|high|critical
            $table->json('evidence')->nullable();
            $table->string('status')->default('pending'); // pending|approved|rejected|auto_approved
            $table->text('decision_note')->nullable();
            $table->string('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('run_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_approvals');
    }
};
