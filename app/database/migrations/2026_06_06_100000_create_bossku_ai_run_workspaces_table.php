<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_run_workspaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->constrained('bossku_ai_runs')->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('bossku_ai_projects')->nullOnDelete();
            $table->string('base_ref')->nullable();
            $table->string('branch_name');
            $table->string('worktree_path');
            $table->string('status')->default('pending'); // pending|ready|failed|removed
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('run_id');
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_run_workspaces');
    }
};
