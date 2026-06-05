<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_model_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('role')->unique(); // planner|coder|reviewer|researcher|memory-curator|auditor
            $table->foreignUuid('primary_provider_id')->nullable()->constrained('bossku_ai_llm_providers')->nullOnDelete();
            $table->string('primary_model');
            $table->foreignUuid('fallback_provider_id')->nullable()->constrained('bossku_ai_llm_providers')->nullOnDelete();
            $table->string('fallback_model')->nullable();
            $table->json('routing_rules_json')->nullable();
            $table->decimal('monthly_budget_usd', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_model_routes');
    }
};
