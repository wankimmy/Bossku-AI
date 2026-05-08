<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_checklists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->longText('content');
            $table->string('source_path')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_checklists');
    }
};
