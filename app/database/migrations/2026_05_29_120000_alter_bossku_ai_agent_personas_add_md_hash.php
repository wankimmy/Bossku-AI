<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bossku_ai_agent_personas', function (Blueprint $table) {
            // SHA-256 of the agents/*.md content at the time this row was last synced from disk.
            // NULL = seeded from a stub (never read from disk).
            // Non-null = the hash of the .md content that produced the current `content` column.
            // ensurePipelinePersonas() uses this to detect when .md changed without --force.
            $table->string('md_hash', 64)->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('bossku_ai_agent_personas', function (Blueprint $table) {
            $table->dropColumn('md_hash');
        });
    }
};
