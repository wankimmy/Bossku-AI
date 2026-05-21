<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_knowledge_graph_edges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_node_id')->constrained('bossku_ai_knowledge_graph_nodes')->cascadeOnDelete();
            $table->foreignUuid('target_node_id')->constrained('bossku_ai_knowledge_graph_nodes')->cascadeOnDelete();
            $table->string('relation'); // used_in|related_to|conflicts_with|derived_from|references
            $table->float('weight')->default(1.0);
            $table->boolean('is_conflict')->default(false);
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index('source_node_id');
            $table->index('target_node_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_knowledge_graph_edges');
    }
};
