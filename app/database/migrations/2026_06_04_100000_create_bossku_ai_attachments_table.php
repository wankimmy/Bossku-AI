<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->nullable()->constrained('bossku_ai_runs')->nullOnDelete();
            $table->string('original_name');
            $table->string('mime', 191);
            $table->unsignedBigInteger('size');
            $table->string('kind', 32); // text|pdf|image|other
            $table->string('storage_path');
            $table->longText('extracted_text')->nullable();
            $table->timestamps();

            $table->index('run_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_attachments');
    }
};
