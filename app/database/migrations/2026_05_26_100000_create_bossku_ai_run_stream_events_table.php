<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_run_stream_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_id');
            $table->unsignedBigInteger('seq');
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('run_id')->references('id')->on('bossku_ai_runs')->cascadeOnDelete();
            $table->unique(['run_id', 'seq']);
            $table->index(['run_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_run_stream_events');
    }
};
