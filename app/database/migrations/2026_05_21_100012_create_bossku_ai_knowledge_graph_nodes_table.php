<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_knowledge_graph_nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type'); // run|memory|skill|agent|file|concept
            $table->string('label');
            $table->uuid('source_id')->nullable();   // FK to source entity
            $table->string('source_type')->nullable();
            $table->float('confidence')->default(1.0);
            $table->boolean('has_conflict')->default(false);
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_knowledge_graph_nodes');
    }
};
