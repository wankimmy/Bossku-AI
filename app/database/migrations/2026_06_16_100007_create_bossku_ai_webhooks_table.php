<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Outbound webhook subscription. The kernel POSTs to `url` when one of the
     * subscribed `events` fires (run.completed, run.interrupted, checkpoint.created).
     */
    public function up(): void
    {
        Schema::create('bossku_ai_webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('url');
            $table->json('events'); // list of event names
            $table->string('secret')->nullable(); // HMAC signing secret
            $table->boolean('enabled')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_webhooks');
    }
};
