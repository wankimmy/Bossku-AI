<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('bossku_ai_projects')->cascadeOnDelete();
            $table->uuid('parent_goal_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('active'); // active | achieved | paused | abandoned
            $table->string('priority')->default('medium');
            $table->string('target_metric')->nullable(); // e.g. "$1M MRR", "1000 signups"
            $table->decimal('target_value', 18, 4)->nullable();
            $table->decimal('current_value', 18, 4)->nullable();
            $table->unsignedTinyInteger('progress')->default(0); // 0–100
            $table->timestamp('due_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index('parent_goal_id');
        });

        Schema::table('bossku_ai_goals', function (Blueprint $table) {
            $table->foreign('parent_goal_id')
                ->references('id')
                ->on('bossku_ai_goals')
                ->nullOnDelete();
        });

        Schema::table('bossku_ai_work_issues', function (Blueprint $table) {
            $table->foreignUuid('goal_id')->nullable()->after('project_id')
                ->constrained('bossku_ai_goals')->nullOnDelete();
            $table->index(['goal_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('bossku_ai_work_issues', function (Blueprint $table) {
            $table->dropForeign(['goal_id']);
            $table->dropIndex(['goal_id', 'status']);
            $table->dropColumn('goal_id');
        });

        Schema::dropIfExists('bossku_ai_goals');
    }
};
