<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_skill_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('skill_id')->constrained('bossku_ai_skills')->cascadeOnDelete();
            $table->string('link_type'); // rule, playbook, checklist
            $table->uuid('linked_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['skill_id', 'link_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_skill_links');
    }
};
