<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bossku_ai_specialist_agents', function (Blueprint $table) {
            $table->foreignUuid('reports_to_agent_id')->nullable()->after('project_id')
                ->constrained('bossku_ai_specialist_agents')->nullOnDelete();
            $table->string('department')->nullable()->after('role_slug');
            $table->boolean('can_create_agents')->default(false)->after('council_enabled');
            $table->string('budget_policy')->nullable()->after('can_create_agents');
        });

        Schema::table('bossku_ai_work_issues', function (Blueprint $table) {
            $table->foreignUuid('parent_issue_id')->nullable()->after('project_id')
                ->constrained('bossku_ai_work_issues')->nullOnDelete();
            $table->foreignUuid('assignee_agent_id')->nullable()->after('assignee_role_slug')
                ->constrained('bossku_ai_specialist_agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bossku_ai_work_issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignee_agent_id');
            $table->dropConstrainedForeignId('parent_issue_id');
        });

        Schema::table('bossku_ai_specialist_agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reports_to_agent_id');
            $table->dropColumn(['department', 'can_create_agents', 'budget_policy']);
        });
    }
};
