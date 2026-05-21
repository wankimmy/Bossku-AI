<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bossku_ai_runs', function (Blueprint $table) {
            $table->float('audit_score')->nullable()->after('metadata');
            $table->string('risk_level')->nullable()->after('audit_score'); // low|medium|high|critical
            $table->foreignUuid('soul_version_id')->nullable()->after('risk_level')->constrained('bossku_ai_soul_versions')->nullOnDelete();
            $table->decimal('estimated_cost', 12, 8)->nullable()->after('soul_version_id');
            $table->string('selected_skill_name')->nullable()->after('estimated_cost');
        });
    }

    public function down(): void
    {
        Schema::table('bossku_ai_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('soul_version_id');
            $table->dropColumn(['audit_score', 'risk_level', 'estimated_cost', 'selected_skill_name']);
        });
    }
};
