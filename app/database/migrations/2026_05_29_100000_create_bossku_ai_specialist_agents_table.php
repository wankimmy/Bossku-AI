<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_specialist_agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('bossku_ai_projects')->cascadeOnDelete();
            $table->string('role_slug');
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->json('trigger_keywords')->nullable();
            $table->longText('persona_content')->nullable();
            $table->foreignUuid('linked_skill_id')->nullable()->constrained('bossku_ai_skills')->nullOnDelete();
            $table->string('approval_status')->default('draft'); // draft|pending_review|approved|rejected|archived
            $table->unsignedSmallInteger('pixel_palette')->default(0);
            $table->integer('pixel_hue_shift')->default(0);
            $table->string('seat_id')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'role_slug']);
            $table->index(['project_id', 'approval_status']);
            $table->index('linked_skill_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_specialist_agents');
    }
};
