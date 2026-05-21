<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_usage_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->nullable()->constrained('bossku_ai_runs')->nullOnDelete();
            $table->foreignUuid('run_step_id')->nullable()->constrained('bossku_ai_run_steps')->nullOnDelete();
            $table->string('provider');
            $table->string('model');
            $table->string('role')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 12, 8)->default(0);
            $table->string('call_type')->default('chat'); // chat|embed|complete
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('run_id');
            $table->index(['provider', 'model']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_usage_events');
    }
};
