<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_skill_candidates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->longText('draft_content')->nullable();
            $table->string('approval_status')->default('draft'); // draft|pending_review|approved|rejected|archived|deprecated
            $table->float('quality_score')->nullable();
            $table->unsignedInteger('source_run_count')->default(0);
            $table->json('source_run_ids')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignUuid('approved_skill_id')->nullable()->constrained('bossku_ai_skills')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_skill_candidates');
    }
};
