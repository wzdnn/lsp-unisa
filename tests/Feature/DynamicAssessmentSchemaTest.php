<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DynamicAssessmentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_dynamic_assessment_schema_is_complete(): void
    {
        foreach ([
            'lsp_assessment_forms',
            'lsp_assessment_form_versions',
            'lsp_assessment_form_prodi',
            'lsp_assessment_sections',
            'lsp_assessment_questions',
            'lsp_assessment_question_units',
            'lsp_assessment_processes',
            'lsp_assessment_assignments',
            'lsp_assessment_answers',
            'lsp_assessment_evidences',
            'lsp_assessment_reviews',
            'lsp_assessment_decisions',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumns('lsp_assessment_questions', [
            'type', 'options', 'settings', 'kdlsp_skema_unitkompetensi_elemen_kriteria',
        ]));
        $this->assertTrue(Schema::hasColumn('lsp_assessment_forms', 'kdlsp_skema'));
        $this->assertTrue(Schema::hasColumns('lsp_assessment_form_prodi', ['form_id', 'kdunitkerja']));
        $this->assertTrue(Schema::hasColumns('lsp_assessment_question_units', [
            'question_id', 'kdlsp_skema_unitkompetensi',
            'kdlsp_skema_unitkompetensi_elemen', 'kdlsp_skema_unitkompetensi_elemen_kriteria',
        ]));
        $this->assertTrue(Schema::hasColumns('lsp_assessment_decisions', [
            'result', 'is_published', 'decided_at',
        ]));
        $this->assertTrue(Schema::hasColumns('lsp_assessment_assignments', [
            'revision_notes', 'revision_requested_at', 'completed_at',
        ]));
        $this->assertTrue(Schema::hasColumns('lsp_assessment_processes', [
            'final_result', 'decision_notes', 'decided_by', 'decided_at', 'result_published_at',
        ]));
    }
}
