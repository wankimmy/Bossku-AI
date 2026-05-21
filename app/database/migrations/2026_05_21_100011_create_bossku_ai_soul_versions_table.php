<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_soul_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('version'); // semver e.g. v1.0.0
            $table->longText('content');
            $table->boolean('active')->default(false);
            $table->text('change_summary')->nullable();
            $table->json('suggestions_applied')->nullable();
            $table->timestamps();

            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_soul_versions');
    }
};
