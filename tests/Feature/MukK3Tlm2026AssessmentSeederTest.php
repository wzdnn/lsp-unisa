<?php

namespace Tests\Feature;

use Database\Seeders\MukK3Tlm2026AssessmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MukK3Tlm2026AssessmentSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('lsp_skema', function (Blueprint $table) {
            $table->id('kdlsp_skema'); $table->string('skema'); $table->string('no_skema')->nullable();
            $table->boolean('isActive')->default(true); $table->timestamps();
        });
        Schema::create('pt_unitkerja', function (Blueprint $table) {
            $table->id('kdunitkerja'); $table->string('unitkerja'); $table->string('unitkerjapendek')->nullable();
            $table->integer('leveling')->nullable();
        });
        Schema::create('lsp_skema_unitkompetensi', function (Blueprint $table) {
            $table->id('kdlsp_skema_unitkompetensi'); $table->unsignedBigInteger('kdlsp_skema');
            $table->string('kode_unit'); $table->string('judul_unit'); $table->string('standar_kompetensi_kerja'); $table->timestamps();
        });
        Schema::create('lsp_skema_unitkompetensi_elemen', function (Blueprint $table) {
            $table->id('kdlsp_skema_unitkompetensi_elemen'); $table->unsignedBigInteger('kdlsp_skema_unitkompetensi');
            $table->text('elemen'); $table->timestamps();
        });
        Schema::create('lsp_skema_unitkompetensi_elemen_kriteria', function (Blueprint $table) {
            $table->id('kdlsp_skema_unitkompetensi_elemen_kriteria'); $table->unsignedBigInteger('kdlsp_skema_unitkompetensi_elemen');
            $table->text('kriteria'); $table->timestamps();
        });
        DB::table('pt_unitkerja')->insert([
            'kdunitkerja' => 52, 'unitkerja' => 'Program Studi Teknologi Laboratorium Medis',
            'unitkerjapendek' => 'TLM', 'leveling' => 4,
        ]);
    }

    public function test_it_seeds_published_master_muk_forms_idempotently(): void
    {
        $this->seed(MukK3Tlm2026AssessmentSeeder::class);
        $this->seed(MukK3Tlm2026AssessmentSeeder::class);

        $this->assertDatabaseCount('lsp_assessment_forms', 17);
        $this->assertDatabaseCount('lsp_assessment_form_versions', 17);
        $this->assertDatabaseHas('lsp_assessment_forms', [
            'code' => 'FR.APL.02',
            'stage' => 'pra_asesmen',
            'filled_by' => 'bersama',
            'reviewed_by' => 'asesor',
        ]);
        $this->assertDatabaseHas('lsp_assessment_forms', [
            'code' => 'FR.IA.01',
            'stage' => 'asesmen',
            'filled_by' => 'asesor',
        ]);
        $this->assertDatabaseHas('lsp_assessment_forms', [
            'code' => 'FR.AK.03',
            'stage' => 'pasca_asesmen',
            'filled_by' => 'asesi',
        ]);
        $this->assertDatabaseHas('lsp_assessment_form_versions', [
            'version' => 2026081002,
            'status' => 'published',
        ]);
        $this->assertSame(437, \DB::table('lsp_assessment_questions')->count());
        $this->assertSame(91, \DB::table('lsp_assessment_questions')->where('code', 'like', 'IA01_%_KUK_%')->count());
        $this->assertDatabaseCount('lsp_skema_unitkompetensi', 9);
        $this->assertDatabaseCount('lsp_skema_unitkompetensi_elemen', 27);
        $this->assertDatabaseCount('lsp_skema_unitkompetensi_elemen_kriteria', 91);
        $this->assertDatabaseCount('lsp_assessment_form_prodi', 17);
        $this->assertDatabaseCount('lsp_assessment_question_units', 268);
        $this->assertSame(91, DB::table('lsp_assessment_questions')->where('code', 'like', 'IA01_%_KUK_%')
            ->whereNotNull('kdlsp_skema_unitkompetensi')->whereNotNull('kdlsp_skema_unitkompetensi_elemen')
            ->whereNotNull('kdlsp_skema_unitkompetensi_elemen_kriteria')->count());
        foreach (['FR.APL.01', 'FR.APL.02', 'FR.MAPA.01', 'FR.MAPA.02', 'FR.AK.01', 'FR.AK.02', 'FR.AK.03', 'FR.AK.04', 'FR.AK.05', 'FR.AK.06', 'FR.AK.07', 'FR.IA.01', 'FR.IA.02', 'FR.IA.03', 'FR.IA.07', 'FR.VA', 'MUK.CHECKLIST'] as $code) {
            $this->assertDatabaseHas('lsp_assessment_forms', ['code' => $code]);
        }
        foreach ([
            'IA02_S1_TUGAS_1', 'IA02_S1_TUGAS_2', 'IA02_S2_TUGAS_1', 'IA02_S2_TUGAS_2', 'IA02_S2_TUGAS_3', 'IA02_S3_TUGAS_1', 'IA02_S3_TUGAS_2',
            'IA03_1', 'IA03_2', 'IA03_3', 'IA03_4', 'IA07_1', 'IA07_2', 'IA07_3',
            'AK04_DIJELASKAN', 'AK04_DISKUSI', 'AK04_ORANG_LAIN',
            'AK07_A1', 'AK07_A2', 'AK07_A3', 'AK07_A4', 'AK07_A5', 'AK07_A6', 'AK07_A7', 'AK07_A8', 'AK07_B1', 'AK07_B2', 'AK07_B3',
            'VA_ASPEK_1', 'VA_ASPEK_2', 'VA_ASPEK_3', 'VA_ASPEK_4', 'VA_ASPEK_5', 'VA_ASPEK_6', 'VA_ASPEK_7', 'VA_ASPEK_8',
        ] as $questionCode) {
            $this->assertDatabaseHas('lsp_assessment_questions', ['code' => $questionCode]);
        }
    }
}
