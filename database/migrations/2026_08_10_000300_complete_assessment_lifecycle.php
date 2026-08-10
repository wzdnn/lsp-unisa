<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lsp_assessment_processes', function (Blueprint $table) {
            $table->enum('final_result', ['competent', 'not_competent'])->nullable()->after('status');
            $table->text('decision_notes')->nullable()->after('final_result');
            $table->unsignedBigInteger('decided_by')->nullable()->index()->after('decision_notes');
            $table->timestamp('decided_at')->nullable()->after('decided_by');
            $table->timestamp('result_published_at')->nullable()->after('decided_at');
        });
    }

    public function down(): void
    {
        Schema::table('lsp_assessment_processes', function (Blueprint $table) {
            $table->dropIndex(['decided_by']);
            $table->dropColumn(['final_result', 'decision_notes', 'decided_by', 'decided_at', 'result_published_at']);
        });
    }
};
