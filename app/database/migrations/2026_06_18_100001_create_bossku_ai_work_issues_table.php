<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_work_issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('bossku_ai_projects')->cascadeOnDelete();
            $table->foreignUuid('run_id')->nullable()->constrained('bossku_ai_runs')->nullOnDelete();
            $table->string('source_plan_item_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('todo');
            $table->string('priority')->default('medium');
            $table->string('approval_state')->default('approved');
            $table->string('assignee_role_slug')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'source_plan_item_id'], 'bossku_work_issue_source_unique');
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'assignee_role_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_work_issues');
    }
};
