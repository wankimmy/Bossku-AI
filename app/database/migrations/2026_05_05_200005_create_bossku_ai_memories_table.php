<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $pg = Schema::connection(null)->getConnection()->getDriverName() === 'pgsql';

        if ($pg) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }

        Schema::create('bossku_ai_memories', function (Blueprint $table) use ($pg) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->text('content');
            $table->text('human_summary')->nullable();
            if ($pg) {
                $table->jsonb('metadata')->nullable();
            }
            else {
                $table->json('metadata')->nullable();
            }
            $table->json('tags')->nullable();
            $table->decimal('confidence', 8, 4)->nullable();
            $table->string('source')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();
        });

        if ($pg) {
            DB::statement('ALTER TABLE bossku_ai_memories ADD COLUMN embedding vector(1536) NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_memories');
    }
};
