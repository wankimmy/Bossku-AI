<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_feedback_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->nullable()->constrained('bossku_ai_runs')->nullOnDelete();
            $table->string('report_type'); // bug_report|improvement_suggestion|ci_failure|review_comment
            $table->string('dedupe_key')->index();
            $table->text('summary');
            $table->json('evidence')->nullable();
            $table->float('confidence')->default(0.5);
            $table->string('status')->default('open'); // open|applied|dismissed
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['dedupe_key', 'run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_feedback_reports');
    }
};
