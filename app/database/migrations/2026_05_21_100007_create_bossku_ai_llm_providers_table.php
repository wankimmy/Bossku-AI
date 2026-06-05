<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_llm_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type'); // anthropic|openai|ollama|openai_compatible|custom
            $table->string('base_url')->nullable();
            $table->text('api_key_encrypted')->nullable(); // Crypt::encryptString
            $table->string('api_key_env')->nullable();     // env var name — takes precedence
            $table->json('available_models')->nullable();
            $table->json('routing_rules_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_health_check_at')->nullable();
            $table->string('health_status')->default('unknown'); // healthy|degraded|down|unknown
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_llm_providers');
    }
};
