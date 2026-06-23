<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atomic task checkout table. Ported from paperclip's atomic-checkout pattern:
 * a single SQL conditional update is the lock primitive that prevents two
 * concurrent agents from working the same task. 0 rows affected → 409 Conflict.
 *
 * The checkout row records: which task (by reference id), which agent owns it,
 * the run that acquired it, the status, and timestamps. The atomicCheckout()
 * method on TaskCheckoutService does a single UPDATE ... WHERE status IN (...)
 * AND (assignee IS NULL OR assignee = ?) — a compare-and-set lock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bossku_ai_task_checkouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('checkoutable_type');
            $table->uuid('checkoutable_id');
            $table->string('assignee')->nullable()->index();
            $table->uuid('run_id')->nullable()->index();
            $table->string('status')->default('available')->index();
            $table->string('lock_token')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['checkoutable_type', 'checkoutable_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bossku_ai_task_checkouts');
    }
};