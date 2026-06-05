<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bossku_ai_run_steps', function (Blueprint $table) {
            $table->text('safe_reasoning_summary')->nullable()->after('metadata');
            $table->decimal('cost', 12, 8)->nullable()->after('safe_reasoning_summary');
            // provider and skill already exist as provider/skill_name — skip duplicates
        });
    }

    public function down(): void
    {
        Schema::table('bossku_ai_run_steps', function (Blueprint $table) {
            $table->dropColumn(['safe_reasoning_summary', 'cost']);
        });
    }
};
