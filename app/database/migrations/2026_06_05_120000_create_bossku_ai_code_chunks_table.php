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

        Schema::create('bossku_ai_code_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->nullable()->index();
            $table->string('path', 1024);
            $table->string('language', 32)->nullable();
            $table->unsignedInteger('chunk_index')->default(0);
            $table->unsignedInteger('start_line')->nullable();
            $table->unsignedInteger('end_line')->nullable();
            $table->text('content');
            // sha256 of the chunk content (dedupe) and of the whole file (change detection).
            $table->string('content_hash', 64)->index();
            $table->string('file_hash', 64)->index();
            $table->unsignedInteger('token_estimate')->default(0);
            // sqlite (and pgsql fallback) embedding storage; pgsql also gets a native vector column below.
            $table->longText('embedding_json')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'path']);
            $table->index(['project_id', 'file_hash']);
        });

        if ($pg) {
            DB::statement('ALTER TABLE bossku_ai_code_chunks ADD COLUMN embedding vector(1536) NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_code_chunks');
    }
};
