<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bossku_ai_skills', function (Blueprint $table) {
            $table->float('quality_score')->nullable()->after('is_active');
            $table->float('feedback_score')->nullable()->after('quality_score');
            $table->string('approval_status')->default('approved')->after('feedback_score');
            $table->float('confidence')->default(1.0)->after('approval_status');
            $table->string('version')->default('1.0.0')->after('confidence');
            $table->unsignedInteger('usage_count')->default(0)->after('version');
            $table->timestamp('last_used_at')->nullable()->after('usage_count');
        });
    }

    public function down(): void
    {
        Schema::table('bossku_ai_skills', function (Blueprint $table) {
            $table->dropColumn(['quality_score', 'feedback_score', 'approval_status', 'confidence', 'version', 'usage_count', 'last_used_at']);
        });
    }
};
