<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('scope')->default('global');
            $table->string('skill_name')->nullable()->index();
            $table->string('name');
            $table->text('rule_text');
            $table->string('source_path')->nullable();
            $table->integer('priority')->default(100);
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_rules');
    }
};
