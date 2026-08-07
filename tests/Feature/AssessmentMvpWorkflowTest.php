<?php

namespace Tests\Feature;

use App\Models\AssessmentForm;
use App\Models\AssessmentProcess;
use App\Models\LspUser;
use App\Models\LspApl01Pengajuan;
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
            $table->string('role');
            $table->boolean('isAsesor')->default(false);
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
            $table->timestamps();
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
        $asesi = LspUser::create(['username' => '2310001', 'role' => 'mahasiswa']);
        $assessor = LspUser::create(['username' => 'dosen01', 'role' => 'dosen', 'isAsesor' => true]);
        $skemaId = \DB::table('lsp_skema')->insertGetId(['skema' => 'Petugas K3', 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('lsp_periode_skema')->insert(['kdlsp_periode_skema' => 10, 'kdlsp_skema' => $skemaId, 'created_at' => now(), 'updated_at' => now()]);

        $form = AssessmentForm::create([
            'code' => 'FR.APL.02', 'name' => 'Asesmen Mandiri', 'stage' => 'pra_asesmen',
            'filled_by' => 'asesi', 'reviewed_by' => 'asesor',
        ]);
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
            ->assertOk()->assertJsonPath('status', 'completed');

        $this->assertDatabaseHas('lsp_assessment_processes', [
            'id' => $process->id, 'assessor_id' => $assessor->kdlsp_user, 'current_stage' => 'persiapan_asesmen',
        ]);
    }
}
