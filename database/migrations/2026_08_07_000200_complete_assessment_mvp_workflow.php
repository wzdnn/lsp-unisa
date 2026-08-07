<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lsp_assessment_processes', function (Blueprint $table) {
            $table->unique('kdlsp_apl01_pengajuan', 'assessment_process_apl01_unique');
        });

        Schema::table('lsp_assessment_assignments', function (Blueprint $table) {
            $table->text('revision_notes')->nullable()->after('status');
            $table->timestamp('revision_requested_at')->nullable()->after('reviewed_at');
            $table->timestamp('completed_at')->nullable()->after('revision_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('lsp_assessment_assignments', function (Blueprint $table) {
            $table->dropColumn(['revision_notes', 'revision_requested_at', 'completed_at']);
        });
        Schema::table('lsp_assessment_processes', function (Blueprint $table) {
            $table->dropUnique('assessment_process_apl01_unique');
        });
    }
};
