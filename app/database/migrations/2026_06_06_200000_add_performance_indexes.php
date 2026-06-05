<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, list<string>> */
    private array $added = [];

    public function up(): void
    {
        $this->safeIndex('bossku_ai_runs', ['status']);
        $this->safeIndex('bossku_ai_runs', ['created_at']);
        $this->safeIndex('bossku_ai_runs', ['run_kind']);

        $this->safeIndex('bossku_ai_memories', ['is_active', 'type']);
        $this->safeIndex('bossku_ai_memories', ['confidence']);
        $this->safeIndex('bossku_ai_memories', ['created_at']);

        $this->safeIndex('bossku_ai_run_steps', ['status']);
        $this->safeIndex('bossku_ai_run_steps', ['type']);

        $this->safeIndex('bossku_ai_agent_messages', ['run_id', 'created_at']);
        $this->safeIndex('bossku_ai_agent_messages', ['agent']);

        $this->safeIndex('bossku_ai_knowledge_graph_nodes', ['source_type', 'source_id']);
        $this->safeIndex('bossku_ai_knowledge_graph_nodes', ['type']);

        $this->safeIndex('bossku_ai_knowledge_graph_edges', ['source_node_id', 'target_node_id', 'relation']);
    }

    public function down(): void
    {
        // Only drop what we added (safeIndex tracks this).
        foreach ($this->added as $table => $indexes) {
            Schema::table($table, function (Blueprint $t) use ($indexes) {
                foreach ($indexes as $indexName) {
                    $t->dropIndex($indexName);
                }
            });
        }
    }

    /** Create index only if it does not already exist (SQLite + pgsql safe). */
    private function safeIndex(string $table, array $columns): void
    {
        $indexName = $table.'_'.implode('_', $columns).'_index';

        $exists = match (DB::getDriverName()) {
            'sqlite' => (bool) DB::selectOne(
                "SELECT 1 FROM sqlite_master WHERE type='index' AND name=?",
                [$indexName]
            ),
            'pgsql' => (bool) DB::selectOne(
                "SELECT 1 FROM pg_indexes WHERE indexname = ?",
                [$indexName]
            ),
            default => false,
        };

        if ($exists) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($columns) {
            $t->index($columns);
        });

        $this->added[$table][] = $indexName;
    }
};
