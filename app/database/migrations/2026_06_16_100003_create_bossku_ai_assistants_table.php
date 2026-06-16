<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An assistant is a saved, runnable configuration: which graph to run plus
     * model-route / persona / option overrides. LangGraph-server parity.
     */
    public function up(): void
    {
        Schema::create('bossku_ai_assistants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('graph')->default('default_pipeline'); // graph name to run
            $table->json('config')->nullable();                   // route/persona/option overrides
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_assistants');
    }
};
