<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bossku_ai_memories', 'embedding_json')) {
            return;
        }

        Schema::table('bossku_ai_memories', function (Blueprint $table) {
            $table->text('embedding_json')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('bossku_ai_memories', 'embedding_json')) {
            return;
        }

        Schema::table('bossku_ai_memories', function (Blueprint $table) {
            $table->dropColumn('embedding_json');
        });
    }
};
