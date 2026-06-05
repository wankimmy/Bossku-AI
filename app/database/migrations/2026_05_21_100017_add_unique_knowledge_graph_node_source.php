<?php

use App\Services\Graph\KnowledgeGraphDedup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        app(KnowledgeGraphDedup::class)->prune();

        Schema::table('bossku_ai_knowledge_graph_nodes', function (Blueprint $table) {
            $table->unique(['source_type', 'source_id'], 'bossku_kg_nodes_source_unique');
        });

        Schema::table('bossku_ai_knowledge_graph_edges', function (Blueprint $table) {
            $table->unique(
                ['source_node_id', 'target_node_id', 'relation'],
                'bossku_kg_edges_triple_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('bossku_ai_knowledge_graph_edges', function (Blueprint $table) {
            $table->dropUnique('bossku_kg_edges_triple_unique');
        });

        Schema::table('bossku_ai_knowledge_graph_nodes', function (Blueprint $table) {
            $table->dropUnique('bossku_kg_nodes_source_unique');
        });
    }
};
