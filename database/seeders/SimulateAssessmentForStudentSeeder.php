<?php

namespace Database\Seeders;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentFormVersion;
use App\Models\AssessmentProcess;
use App\Models\LspApl01Pengajuan;
use App\Models\LspPeriodeSkema;
use App\Models\LspUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SimulateAssessmentForStudentSeeder extends Seeder
{
    private const NIM = '2211501008';
    private const SCHEME_ID = 4;
    private const ASSESSOR_ID = 7;
    private const PRE_ASSESSMENT_FORMS = ['FR.APL.02', 'FR.MAPA.01', 'FR.MAPA.02', 'FR.AK.01', 'FR.AK.07'];
    private const ASSESSMENT_FORMS = ['FR.IA.01', 'FR.IA.02', 'FR.IA.03', 'FR.IA.07'];

    public function run(): void
    {
        $asesi = LspUser::where('username', self::NIM)->first();
        $periodScheme = LspPeriodeSkema::where('kdlsp_skema', self::SCHEME_ID)->latest('kdlsp_periode_skema')->first();
        $assessor = LspUser::whereKey(self::ASSESSOR_ID)->where('isAsesor', true)
            ->whereIn('role', ['dosen', 'asesor_luar'])->first();

        if (!$asesi || !$periodScheme || !$assessor) {
            throw new RuntimeException('Asesi, periode skema Petugas K3, atau asesor aktif tidak tersedia.');
        }

        $application = LspApl01Pengajuan::where('kdlsp_user', $asesi->kdlsp_user)
            ->where('kdlsp_periode_skema', $periodScheme->kdlsp_periode_skema)->latest('kdlsp_apl01_pengajuan')->first();
        if (!$application) {
            throw new RuntimeException('Pengajuan APL.01 siswa untuk periode skema Petugas K3 tidak tersedia.');
        }

        DB::transaction(function () use ($asesi, $periodScheme, $assessor, $application) {
            $application->update([
                'status' => 'diterima',
                'catatan_admin' => 'Disetujui untuk simulasi alur assessment.',
                'reviewed_at' => $application->reviewed_at ?: now(),
            ]);

            $process = AssessmentProcess::updateOrCreate(
                ['kdlsp_apl01_pengajuan' => $application->kdlsp_apl01_pengajuan],
                [
                    'kdlsp_periode_skema' => $periodScheme->kdlsp_periode_skema,
                    'asesi_id' => $asesi->kdlsp_user,
                    'assessor_id' => $assessor->kdlsp_user,
                    'current_stage' => 'asesmen',
                    'status' => 'active',
                    'started_at' => now(),
                    'completed_at' => null,
                ]
            );

            $process->assignments()->where('assignee_role', 'asesor')
                ->where('assigned_to', '<>', $assessor->kdlsp_user)->delete();

            $this->assignForms($process, self::PRE_ASSESSMENT_FORMS, 'completed');
            $this->assignForms($process, self::ASSESSMENT_FORMS, 'under_review');
        });

        $this->command?->info('NIM '.self::NIM.' berhasil disimulasikan pada tahap asesmen.');
    }

    private function assignForms(AssessmentProcess $process, array $codes, string $status): void
    {
        $versions = AssessmentFormVersion::with('form')
            ->where('status', 'published')
            ->whereHas('form', fn ($query) => $query->where('kdlsp_skema', self::SCHEME_ID)->whereIn('code', $codes))
            ->whereIn('id', fn ($query) => $query->selectRaw('MAX(id)')->from('lsp_assessment_form_versions')
                ->where('status', 'published')->groupBy('form_id'))
            ->get();

        if ($versions->count() !== count($codes)) {
            throw new RuntimeException('Template published untuk simulasi belum lengkap.');
        }

        foreach ($versions as $version) {
            $forAsesi = in_array($version->form->filled_by, ['asesi', 'bersama']);
            AssessmentAssignment::updateOrCreate([
                'process_id' => $process->id,
                'form_version_id' => $version->id,
                'assigned_to' => $forAsesi ? $process->asesi_id : $process->assessor_id,
            ], [
                'assignee_role' => $forAsesi ? 'asesi' : 'asesor',
                'status' => $status,
                'completed_at' => $status === 'completed' ? now() : null,
                'reviewed_at' => $status === 'under_review' ? now() : null,
            ]);
        }
    }
}
