<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('prompt');
            $table->longText('final_output')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('total_latency_ms')->nullable();
            $table->unsignedInteger('total_token_estimate')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_runs');
    }
};
