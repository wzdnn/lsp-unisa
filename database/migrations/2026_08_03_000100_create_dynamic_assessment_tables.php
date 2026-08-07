<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lsp_assessment_forms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->enum('stage', ['pra_asesmen', 'asesmen', 'pasca_asesmen']);
            $table->enum('filled_by', ['asesi', 'asesor', 'bersama', 'admin']);
            $table->enum('reviewed_by', ['asesor', 'admin', 'lead_asesor'])->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('lsp_assessment_form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('lsp_assessment_forms')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->json('settings')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['form_id', 'version']);
        });

        Schema::create('lsp_assessment_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_version_id')->constrained('lsp_assessment_form_versions')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('lsp_assessment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('lsp_assessment_sections')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('type', 40);
            $table->text('label');
            $table->text('instructions')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('kdlsp_skema_unitkompetensi')->nullable()->index();
            $table->unsignedBigInteger('kdlsp_skema_unitkompetensi_elemen')->nullable()->index();
            $table->unsignedBigInteger('kdlsp_skema_unitkompetensi_elemen_kriteria')->nullable()->index();
            $table->json('options')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['section_id', 'code']);
        });

        Schema::create('lsp_assessment_processes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kdlsp_apl01_pengajuan')->nullable()->index();
            $table->unsignedBigInteger('kdlsp_periode_skema')->index();
            $table->unsignedBigInteger('asesi_id')->index();
            $table->unsignedBigInteger('assessor_id')->nullable()->index();
            $table->string('current_stage', 40)->default('pra_asesmen');
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lsp_assessment_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained('lsp_assessment_processes')->cascadeOnDelete();
            $table->foreignId('form_version_id')->constrained('lsp_assessment_form_versions')->restrictOnDelete();
            $table->unsignedBigInteger('assigned_to')->index();
            $table->enum('assignee_role', ['asesi', 'asesor', 'admin']);
            $table->enum('status', ['assigned', 'draft', 'submitted', 'under_review', 'revision_required', 'assessed', 'result_published', 'completed'])->default('assigned');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['process_id', 'form_version_id', 'assigned_to']);
        });

        Schema::create('lsp_assessment_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('lsp_assessment_assignments')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('lsp_assessment_questions')->cascadeOnDelete();
            $table->longText('answer_text')->nullable();
            $table->json('answer_json')->nullable();
            $table->timestamps();
            $table->unique(['assignment_id', 'question_id']);
        });

        Schema::create('lsp_assessment_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('answer_id')->constrained('lsp_assessment_answers')->cascadeOnDelete();
            $table->string('disk', 30)->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        Schema::create('lsp_assessment_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('lsp_assessment_assignments')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('lsp_assessment_questions')->cascadeOnDelete();
            $table->unsignedBigInteger('assessor_id')->index();
            $table->enum('result', ['achieved', 'not_achieved', 'needs_follow_up', 'not_assessed']);
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['assignment_id', 'question_id', 'assessor_id']);
        });

        Schema::create('lsp_assessment_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->unique()->constrained('lsp_assessment_assignments')->cascadeOnDelete();
            $table->unsignedBigInteger('assessor_id')->index();
            $table->enum('result', ['competent', 'not_competent']);
            $table->text('notes')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('decided_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lsp_assessment_decisions');
        Schema::dropIfExists('lsp_assessment_reviews');
        Schema::dropIfExists('lsp_assessment_evidences');
        Schema::dropIfExists('lsp_assessment_answers');
        Schema::dropIfExists('lsp_assessment_assignments');
        Schema::dropIfExists('lsp_assessment_processes');
        Schema::dropIfExists('lsp_assessment_questions');
        Schema::dropIfExists('lsp_assessment_sections');
        Schema::dropIfExists('lsp_assessment_form_versions');
        Schema::dropIfExists('lsp_assessment_forms');
    }
};
