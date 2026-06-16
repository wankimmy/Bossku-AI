<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bossku_ai_runs', function (Blueprint $table) {
            $table->uuid('thread_id')->nullable()->after('parent_run_id');
            $table->uuid('assistant_id')->nullable()->after('thread_id');
            $table->index('thread_id');
        });
    }

    public function down(): void
    {
        Schema::table('bossku_ai_runs', function (Blueprint $table) {
            $table->dropIndex(['thread_id']);
            $table->dropColumn(['thread_id', 'assistant_id']);
        });
    }
};
