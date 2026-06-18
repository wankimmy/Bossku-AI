<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bossku_ai_specialist_agents', function (Blueprint $table) {
            $table->boolean('is_company_staff')->default(false)->after('approval_status');
            $table->boolean('staff_active')->default(true)->after('is_company_staff');
            $table->boolean('council_enabled')->default(true)->after('staff_active');
            $table->string('runtime_mode')->default('advisory')->after('council_enabled');
            $table->unsignedSmallInteger('staff_sort_order')->default(100)->after('runtime_mode');

            $table->index(['project_id', 'is_company_staff', 'staff_active'], 'bossku_staff_project_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bossku_ai_specialist_agents', function (Blueprint $table) {
            $table->dropIndex('bossku_staff_project_active_idx');
            $table->dropColumn([
                'is_company_staff',
                'staff_active',
                'council_enabled',
                'runtime_mode',
                'staff_sort_order',
            ]);
        });
    }
};
