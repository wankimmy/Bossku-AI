<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bossku_ai_memories', function (Blueprint $table) {
            $table->json('conflicting_memory_ids_json')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('bossku_ai_memories', function (Blueprint $table) {
            $table->dropColumn('conflicting_memory_ids_json');
        });
    }
};
