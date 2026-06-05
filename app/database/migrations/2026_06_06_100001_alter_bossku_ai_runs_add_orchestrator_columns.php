<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bossku_ai_runs', function (Blueprint $table) {
            $table->foreignUuid('parent_run_id')->nullable()->after('id')
                ->constrained('bossku_ai_runs')->nullOnDelete();
            $table->string('run_kind')->default('standard')->after('status'); // standard|supervisor|child|cli_session
            $table->unsignedSmallInteger('supervisor_slot')->nullable()->after('run_kind');
            $table->index(['parent_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('bossku_ai_runs', function (Blueprint $table) {
            $table->dropForeign(['parent_run_id']);
            $table->dropIndex(['parent_run_id', 'status']);
            $table->dropColumn(['parent_run_id', 'run_kind', 'supervisor_slot']);
        });
    }
};
