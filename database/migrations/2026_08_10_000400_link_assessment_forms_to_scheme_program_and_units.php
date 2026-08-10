<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lsp_assessment_forms', function (Blueprint $table) {
            $table->unsignedBigInteger('kdlsp_skema')->nullable()->index()->after('id');
        });

        Schema::create('lsp_assessment_form_prodi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('lsp_assessment_forms')->cascadeOnDelete();
            $table->unsignedBigInteger('kdunitkerja')->index();
            $table->timestamps();
            $table->unique(['form_id', 'kdunitkerja'], 'assessment_form_prodi_unique');
        });

        Schema::create('lsp_assessment_question_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('lsp_assessment_questions')->cascadeOnDelete();
            $table->unsignedBigInteger('kdlsp_skema_unitkompetensi')->index();
            $table->unsignedBigInteger('kdlsp_skema_unitkompetensi_elemen')->nullable()->index();
            $table->unsignedBigInteger('kdlsp_skema_unitkompetensi_elemen_kriteria')->nullable()->index();
            $table->timestamps();
            $table->unique([
                'question_id', 'kdlsp_skema_unitkompetensi',
                'kdlsp_skema_unitkompetensi_elemen', 'kdlsp_skema_unitkompetensi_elemen_kriteria',
            ], 'assessment_question_unit_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lsp_assessment_question_units');
        Schema::dropIfExists('lsp_assessment_form_prodi');
        Schema::table('lsp_assessment_forms', function (Blueprint $table) {
            $table->dropIndex(['kdlsp_skema']);
            $table->dropColumn('kdlsp_skema');
        });
    }
};
