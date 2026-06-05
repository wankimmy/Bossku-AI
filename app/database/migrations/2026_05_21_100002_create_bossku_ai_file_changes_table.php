<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_file_changes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->constrained('bossku_ai_runs')->cascadeOnDelete();
            $table->foreignUuid('run_step_id')->nullable()->constrained('bossku_ai_run_steps')->nullOnDelete();
            $table->string('file_path');
            $table->string('change_type'); // created|modified|deleted|renamed
            $table->longText('patch')->nullable();
            $table->text('reason')->nullable();
            $table->string('agent')->nullable();
            $table->text('audit_note')->nullable();
            $table->string('approval_status')->default('pending'); // pending|approved|rejected
            $table->timestamps();

            $table->index('run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_file_changes');
    }
};
