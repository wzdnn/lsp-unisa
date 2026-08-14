<?php

namespace Tests\Feature;

use App\Models\AssessmentForm;
use App\Models\AssessmentProcess;
use App\Models\LspUser;
use App\Models\LspApl01Pengajuan;
use App\Services\AssessmentProcessService;
use Database\Seeders\MukK3Tlm2026AssessmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AssessmentMvpWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('lsp_user', function (Blueprint $table) {
            $table->id('kdlsp_user');
            $table->string('username')->unique();
            $table->string('password')->nullable();
            $table->string('role');
            $table->boolean('isAsesor')->default(false);
            $table->unsignedBigInteger('kdunit')->nullable();
            $table->unsignedBigInteger('kdperson')->nullable();
            $table->timestamps();
        });
        Schema::create('pt_person', function (Blueprint $table) {
            $table->id('kdperson');
            $table->string('namalengkap')->nullable();
        });
        Schema::create('lsp_skema', function (Blueprint $table) {
            $table->id('kdlsp_skema');
            $table->string('skema');
            $table->string('no_skema')->nullable();
            $table->boolean('isActive')->default(true);
            $table->timestamps();
        });
        Schema::create('pt_unitkerja', function (Blueprint $table) {
            $table->id('kdunitkerja');
            $table->string('unitkerja');
            $table->string('unitkerjapendek')->nullable();
        });
        Schema::create('lsp_skema_unitkompetensi', function (Blueprint $table) {
            $table->id('kdlsp_skema_unitkompetensi'); $table->unsignedBigInteger('kdlsp_skema');
            $table->string('kode_unit'); $table->string('judul_unit'); $table->string('standar_kompetensi_kerja'); $table->timestamps();
        });
        Schema::create('lsp_skema_unitkompetensi_elemen', function (Blueprint $table) {
            $table->id('kdlsp_skema_unitkompetensi_elemen'); $table->unsignedBigInteger('kdlsp_skema_unitkompetensi'); $table->text('elemen'); $table->timestamps();
        });
        Schema::create('lsp_skema_unitkompetensi_elemen_kriteria', function (Blueprint $table) {
            $table->id('kdlsp_skema_unitkompetensi_elemen_kriteria'); $table->unsignedBigInteger('kdlsp_skema_unitkompetensi_elemen'); $table->text('kriteria'); $table->timestamps();
        });
        Schema::create('lsp_periode_skema', function (Blueprint $table) {
            $table->id('kdlsp_periode_skema');
            $table->unsignedBigInteger('kdlsp_skema');
            $table->timestamps();
        });
        Schema::create('lsp_apl01_pengajuan', function (Blueprint $table) {
            $table->id('kdlsp_apl01_pengajuan');
            $table->unsignedBigInteger('kdlsp_user');
            $table->unsignedBigInteger('kdlsp_periode_skema');
            $table->string('status')->default('menunggu_review');
            $table->text('catatan_admin')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_accepting_apl01_creates_only_one_assessment_process(): void
    {
        $asesi = LspUser::create(['username' => '2310002', 'role' => 'mahasiswa']);
        $application = LspApl01Pengajuan::create([
            'kdlsp_user' => $asesi->kdlsp_user,
            'kdlsp_periode_skema' => 22,
            'status' => 'menunggu_review',
        ]);

        $payload = ['status' => 'diterima', 'catatan_admin' => 'Persyaratan lengkap'];
        $this->withSession(['user' => ['username' => 'admin1', 'role' => 'admin']])
            ->patchJson("/api/apl01-pengajuan/{$application->kdlsp_apl01_pengajuan}/review", $payload)
            ->assertOk()
            ->assertJsonPath('assessment_process.current_stage', 'pra_asesmen');
        $this->withSession(['user' => ['username' => 'admin1', 'role' => 'admin']])
            ->patchJson("/api/apl01-pengajuan/{$application->kdlsp_apl01_pengajuan}/review", $payload)
            ->assertOk();

        $this->assertDatabaseCount('lsp_assessment_processes', 1);
    }

    public function test_apl02_runs_from_assignment_through_revision_and_completed_review(): void
    {
        \DB::table('pt_unitkerja')->insert(['kdunitkerja' => 52, 'unitkerja' => 'Program Studi Teknologi Laboratorium Medis']);
        $asesi = LspUser::create(['username' => '2310001', 'role' => 'mahasiswa', 'kdunit' => 52]);
        $assessor = LspUser::create(['username' => 'dosen01', 'role' => 'dosen', 'isAsesor' => true]);
        $skemaId = \DB::table('lsp_skema')->insertGetId(['skema' => 'Petugas K3', 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('lsp_periode_skema')->insert(['kdlsp_periode_skema' => 10, 'kdlsp_skema' => $skemaId, 'created_at' => now(), 'updated_at' => now()]);

        $form = AssessmentForm::create([
            'code' => 'FR.APL.02', 'name' => 'Asesmen Mandiri', 'stage' => 'pra_asesmen',
            'filled_by' => 'asesi', 'reviewed_by' => 'asesor', 'kdlsp_skema' => $skemaId,
        ]);
        $form->programs()->sync([52]);
        $version = $form->versions()->create(['version' => 1, 'status' => 'published', 'published_at' => now()]);
        $section = $version->sections()->create(['title' => 'Unit Kompetensi', 'sort_order' => 0]);
        $question = $section->questions()->create([
            'code' => 'KUK-1.1', 'type' => 'self_assessment', 'label' => 'Saya mampu melakukan tugas',
            'is_required' => true, 'sort_order' => 0,
        ]);
        $process = AssessmentProcess::create([
            'kdlsp_periode_skema' => 10, 'asesi_id' => $asesi->kdlsp_user,
            'current_stage' => 'pra_asesmen', 'status' => 'active', 'started_at' => now(),
        ]);

        $assignment = $this->withSession(['user' => ['username' => 'admin1', 'role' => 'admin']])
            ->postJson("/api/assessment-processes/{$process->id}/assign-apl02", [
                'assessor_id' => $assessor->kdlsp_user,
            ])->assertCreated()->json();

        $assignmentId = $assignment['id'];
        $this->withSession(['user' => ['username' => $asesi->username, 'role' => 'mahasiswa']])
            ->putJson("/api/assessments/{$assignmentId}/answers", ['answers' => [[
                'question_id' => $question->id, 'answer_text' => 'K',
            ]]])->assertOk();
        $this->withSession(['user' => ['username' => $asesi->username, 'role' => 'mahasiswa']])
            ->postJson("/api/assessments/{$assignmentId}/submit")->assertOk()
            ->assertJsonPath('status', 'submitted');

        $review = ['reviews' => [[
            'question_id' => $question->id, 'result' => 'achieved', 'notes' => 'Bukti perlu diperjelas',
        ]]];
        $this->withSession(['user' => ['username' => $assessor->username, 'role' => 'dosen']])
            ->putJson("/api/assessments/{$assignmentId}/review", $review)->assertOk();
        $this->withSession(['user' => ['username' => $assessor->username, 'role' => 'dosen']])
            ->putJson("/api/assessments/{$assignmentId}/request-revision", ['notes' => 'Tambahkan uraian bukti'])
            ->assertOk()->assertJsonPath('status', 'revision_required');

        $this->withSession(['user' => ['username' => $asesi->username, 'role' => 'mahasiswa']])
            ->putJson("/api/assessments/{$assignmentId}/answers", ['answers' => [[
                'question_id' => $question->id, 'answer_text' => 'K - bukti telah diperbarui',
            ]]])->assertOk();
        $this->withSession(['user' => ['username' => $asesi->username, 'role' => 'mahasiswa']])
            ->postJson("/api/assessments/{$assignmentId}/submit")->assertOk();
        $this->withSession(['user' => ['username' => $assessor->username, 'role' => 'dosen']])
            ->putJson("/api/assessments/{$assignmentId}/review", $review)->assertOk();
        $this->withSession(['user' => ['username' => $assessor->username, 'role' => 'dosen']])
            ->putJson("/api/assessments/{$assignmentId}/complete-review")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('templates');

        $this->assertDatabaseHas('lsp_assessment_assignments', [
            'id' => $assignmentId, 'status' => 'under_review',
        ]);
        $this->assertDatabaseHas('lsp_assessment_processes', [
            'id' => $process->id, 'assessor_id' => $assessor->kdlsp_user, 'current_stage' => 'pra_asesmen',
        ]);
    }

    public function test_completed_forms_automatically_advance_the_master_muk_stages(): void
    {
        \DB::table('pt_unitkerja')->insert(['kdunitkerja' => 52, 'unitkerja' => 'Program Studi Teknologi Laboratorium Medis']);
        $this->seed(MukK3Tlm2026AssessmentSeeder::class);
        $schemeId = \DB::table('lsp_skema')->where('skema', 'like', '%Petugas%')->value('kdlsp_skema');
        \DB::table('lsp_periode_skema')->insert(['kdlsp_periode_skema' => 77, 'kdlsp_skema' => $schemeId, 'created_at' => now(), 'updated_at' => now()]);
        $asesi = LspUser::create(['username' => 'asesi-flow', 'role' => 'mahasiswa', 'kdunit' => 52]);
        $assessor = LspUser::create(['username' => 'asesor-flow', 'role' => 'dosen', 'isAsesor' => true]);
        $process = AssessmentProcess::create([
            'kdlsp_periode_skema' => 77,
            'asesi_id' => $asesi->kdlsp_user,
            'assessor_id' => $assessor->kdlsp_user,
            'current_stage' => 'pra_asesmen',
            'status' => 'active',
            'started_at' => now(),
        ]);
        $service = app(AssessmentProcessService::class);
        $apl02 = $service->assignAssessorAndApl02($process, $assessor);
        $apl02->update(['status' => 'completed', 'completed_at' => now()]);
        $service->syncAfterCompletion($apl02);

        $this->assertSame(5, $process->assignments()->count());
        $this->completeCurrentStage($process, 'pra_asesmen', $service);
        $this->assertSame('asesmen', $process->fresh()->current_stage);
        $this->assertSame(4, $this->stageAssignments($process, 'asesmen')->count());

        $this->completeCurrentStage($process, 'asesmen', $service);
        $this->assertSame('pasca_asesmen', $process->fresh()->current_stage);
        $this->assertSame(4, $this->stageAssignments($process, 'pasca_asesmen')->count());

        $this->completeCurrentStage($process, 'pasca_asesmen', $service);
        $this->assertSame('keputusan', $process->fresh()->current_stage);

        $this->withSession(['user' => ['username' => $assessor->username, 'role' => 'dosen']])
            ->putJson("/api/assessment-processes/{$process->id}/decision", [
                'result' => 'competent',
                'notes' => 'Seluruh bukti konsisten dan memenuhi standar.',
                'publish' => true,
            ])
            ->assertOk()
            ->assertJsonPath('final_result', 'competent')
            ->assertJsonPath('status', 'completed');
        $this->assertNotNull($process->fresh()->result_published_at);
    }

    public function test_assessor_can_fill_an_assessor_owned_form_while_it_is_under_review(): void
    {
        $asesi = LspUser::create(['username' => 'asesi-review', 'role' => 'mahasiswa']);
        $assessor = LspUser::create(['username' => 'rochim', 'role' => 'asesor_luar', 'isAsesor' => true]);
        $process = AssessmentProcess::create([
            'kdlsp_periode_skema' => 99, 'asesi_id' => $asesi->kdlsp_user,
            'assessor_id' => $assessor->kdlsp_user, 'current_stage' => 'asesmen',
            'status' => 'active', 'started_at' => now(),
        ]);
        $form = AssessmentForm::create([
            'code' => 'FR.IA.TEST', 'name' => 'Instrumen Uji', 'stage' => 'asesmen',
            'filled_by' => 'asesor', 'reviewed_by' => 'lead_asesor',
        ]);
        $version = $form->versions()->create(['version' => 1, 'status' => 'published']);
        $section = $version->sections()->create(['title' => 'Observasi', 'sort_order' => 0]);
        $question = $section->questions()->create([
            'code' => 'OBS-1', 'type' => 'assessor_observation', 'label' => 'Hasil observasi',
            'is_required' => true, 'sort_order' => 0, 'options' => [['value' => 'ya', 'label' => 'Ya']],
        ]);
        $assignment = $process->assignments()->create([
            'form_version_id' => $version->id, 'assigned_to' => $assessor->kdlsp_user,
            'assignee_role' => 'asesor', 'status' => 'under_review',
        ]);

        $this->withSession(['user' => ['username' => 'rochim', 'role' => 'asesor_luar']])
            ->putJson("/api/assessments/{$assignment->id}/answers", ['answers' => [[
                'question_id' => $question->id, 'answer_text' => 'ya',
            ]]])->assertOk()->assertJsonPath('status', 'under_review');
        $this->assertDatabaseHas('lsp_assessment_answers', [
            'assignment_id' => $assignment->id, 'question_id' => $question->id, 'answer_text' => 'ya',
        ]);
    }

    public function test_competent_decision_is_rejected_when_a_review_is_not_achieved(): void
    {
        $asesi = LspUser::create(['username' => 'asesi-decision', 'role' => 'mahasiswa']);
        $assessor = LspUser::create(['username' => 'asesor-decision', 'role' => 'dosen', 'isAsesor' => true]);
        $process = AssessmentProcess::create([
            'kdlsp_periode_skema' => 101, 'asesi_id' => $asesi->kdlsp_user,
            'assessor_id' => $assessor->kdlsp_user, 'current_stage' => 'keputusan',
            'status' => 'active', 'started_at' => now(),
        ]);
        $form = AssessmentForm::create([
            'code' => 'FR.TEST.DECISION', 'name' => 'Uji Keputusan', 'stage' => 'asesmen',
            'filled_by' => 'asesi', 'reviewed_by' => 'asesor',
        ]);
        $version = $form->versions()->create(['version' => 1, 'status' => 'published']);
        $section = $version->sections()->create(['title' => 'KUK', 'sort_order' => 0]);
        $question = $section->questions()->create([
            'code' => 'KUK-DECISION', 'type' => 'self_assessment', 'label' => 'Kriteria uji',
            'is_required' => true, 'sort_order' => 0,
        ]);
        $assignment = $process->assignments()->create([
            'form_version_id' => $version->id, 'assigned_to' => $asesi->kdlsp_user,
            'assignee_role' => 'asesi', 'status' => 'completed', 'completed_at' => now(),
        ]);
        $assignment->reviews()->create([
            'question_id' => $question->id, 'assessor_id' => $assessor->kdlsp_user,
            'result' => 'not_achieved', 'reviewed_at' => now(),
        ]);

        $this->withSession(['user' => ['username' => $assessor->username, 'role' => 'dosen']])
            ->putJson("/api/assessment-processes/{$process->id}/decision", [
                'result' => 'competent', 'notes' => 'Dicoba kompeten', 'publish' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Keputusan Kompeten tidak dapat diberikan karena masih ada KUK belum tercapai atau memerlukan tindak lanjut');

        $this->assertNull($process->fresh()->final_result);
    }

    public function test_admin_and_unassigned_assessor_cannot_mutate_or_read_foreign_apl01_documents(): void
    {
        $asesi = LspUser::create(['username' => 'asesi-document', 'role' => 'mahasiswa']);
        $otherAssessor = LspUser::create(['username' => 'other-assessor', 'role' => 'dosen', 'isAsesor' => true]);
        $application = LspApl01Pengajuan::create([
            'kdlsp_user' => $asesi->kdlsp_user,
            'kdlsp_periode_skema' => 102,
            'status' => 'draft',
        ]);

        $this->withSession(['user' => ['username' => 'admin1', 'role' => 'admin']])
            ->postJson("/api/apl01-pengajuan/{$application->kdlsp_apl01_pengajuan}/dokumen", [])
            ->assertForbidden();

        $this->withSession(['user' => ['username' => $otherAssessor->username, 'role' => 'dosen']])
            ->getJson("/api/apl01-pengajuan/{$application->kdlsp_apl01_pengajuan}/dokumen")
            ->assertForbidden();
    }

    private function completeCurrentStage(AssessmentProcess $process, string $stage, AssessmentProcessService $service): void
    {
        foreach ($this->stageAssignments($process, $stage) as $assignment) {
            if ($assignment->status !== 'completed') {
                $assignment->update(['status' => 'completed', 'completed_at' => now()]);
            }
            $service->syncAfterCompletion($assignment);
        }
    }

    private function stageAssignments(AssessmentProcess $process, string $stage)
    {
        return $process->assignments()->whereHas('version.form', fn ($query) => $query->where('stage', $stage))->get();
    }
}
