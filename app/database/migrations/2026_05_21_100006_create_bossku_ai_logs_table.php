<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('level')->default('info'); // debug|info|warning|error|critical
            $table->string('channel')->default('app');
            $table->text('message');
            $table->json('context')->nullable();
            $table->foreignUuid('run_id')->nullable()->constrained('bossku_ai_runs')->nullOnDelete();
            $table->string('source')->nullable();
            $table->timestamps();

            $table->index('level');
            $table->index('run_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_logs');
    }
};
