<?php

namespace App\Services;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentFormVersion;
use App\Models\AssessmentProcess;
use App\Models\LspApl01Pengajuan;
use App\Models\LspUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentProcessService
{
    private const AUTO_FORMS = [
        'pra_asesmen' => ['FR.APL.02', 'FR.MAPA.01', 'FR.MAPA.02', 'FR.AK.01', 'FR.AK.07'],
        'asesmen' => ['FR.IA.01', 'FR.IA.02', 'FR.IA.03', 'FR.IA.07'],
        'pasca_asesmen' => ['FR.AK.02', 'FR.AK.03', 'FR.AK.05', 'FR.AK.06'],
    ];

    public function startFromAcceptedApl01(LspApl01Pengajuan $application): AssessmentProcess
    {
        return AssessmentProcess::firstOrCreate(
            ['kdlsp_apl01_pengajuan' => $application->kdlsp_apl01_pengajuan],
            [
                'kdlsp_periode_skema' => $application->kdlsp_periode_skema,
                'asesi_id' => $application->kdlsp_user,
                'current_stage' => 'pra_asesmen',
                'status' => 'active',
                'started_at' => now(),
            ]
        );
    }

    public function assignAssessorAndApl02(AssessmentProcess $process, LspUser $assessor, ?string $dueAt = null): AssessmentAssignment
    {
        if (!in_array($assessor->role, ['dosen', 'asesor_luar']) || !$assessor->isAsesor) {
            throw ValidationException::withMessages(['assessor_id' => 'User yang dipilih bukan asesor aktif']);
        }

        $process->loadMissing('periodeSkema', 'asesi');
        $schemeId = $process->periodeSkema?->kdlsp_skema;
        $programId = $process->asesi?->kdunit;
        if (!$schemeId || !$programId) {
            throw ValidationException::withMessages(['scope' => 'Skema proses atau program studi asesi belum tersedia']);
        }

        $version = AssessmentFormVersion::with('form')
            ->where('status', 'published')
            ->whereHas('form', fn ($query) => $query->where('code', 'FR.APL.02')
                ->whereIn('filled_by', ['asesi', 'bersama'])
                ->where('kdlsp_skema', $schemeId)
                ->whereHas('programs', fn ($programs) => $programs->where('pt_unitkerja.kdunitkerja', $programId)))
            ->latest('version')
            ->first();

        if (!$version) {
            throw ValidationException::withMessages([
                'form' => 'Template FR.APL.02 berstatus published belum tersedia',
            ]);
        }

        return DB::transaction(function () use ($process, $assessor, $version, $dueAt) {
            $process->update(['assessor_id' => $assessor->kdlsp_user, 'current_stage' => 'pra_asesmen']);

            return AssessmentAssignment::updateOrCreate(
                [
                    'process_id' => $process->id,
                    'form_version_id' => $version->id,
                    'assigned_to' => $process->asesi_id,
                ],
                [
                    'assignee_role' => 'asesi',
                    'status' => 'assigned',
                    'due_at' => $dueAt,
                    'revision_notes' => null,
                ]
            );
        });
    }

    public function assignStageForms(AssessmentProcess $process, string $stage, ?string $dueAt = null): void
    {
        if (!isset(self::AUTO_FORMS[$stage])) {
            return;
        }

        $process->loadMissing('periodeSkema', 'asesi');
        $schemeId = $process->periodeSkema?->kdlsp_skema;
        $programId = $process->asesi?->kdunit;
        if (!$schemeId || !$programId) {
            throw ValidationException::withMessages(['scope' => 'Skema proses atau program studi asesi belum tersedia']);
        }

        $versions = AssessmentFormVersion::with('form')
            ->where('status', 'published')
            ->whereHas('form', fn ($query) => $query
                ->where('stage', $stage)
                ->whereIn('code', self::AUTO_FORMS[$stage])
                ->where('kdlsp_skema', $schemeId)
                ->whereHas('programs', fn ($programs) => $programs->where('pt_unitkerja.kdunitkerja', $programId)))
            ->whereIn('id', function ($query) {
                $query->selectRaw('MAX(id)')->from('lsp_assessment_form_versions')
                    ->where('status', 'published')->groupBy('form_id');
            })
            ->get();

        DB::transaction(function () use ($process, $stage, $dueAt, $versions) {
            $process->update(['current_stage' => $stage]);

            foreach ($versions as $version) {
                if ($version->form->code === 'FR.APL.02' && $process->assignments()
                    ->where('form_version_id', $version->id)->exists()) {
                    continue;
                }

                $forAsesi = in_array($version->form->filled_by, ['asesi', 'bersama']);
                $assignedTo = $forAsesi ? $process->asesi_id : $process->assessor_id;
                if (!$assignedTo) {
                    continue;
                }

                AssessmentAssignment::firstOrCreate([
                    'process_id' => $process->id,
                    'form_version_id' => $version->id,
                    'assigned_to' => $assignedTo,
                ], [
                    'assignee_role' => $forAsesi ? 'asesi' : 'asesor',
                    'status' => 'assigned',
                    'due_at' => $dueAt,
                ]);
            }
        });
    }

    public function syncAfterCompletion(AssessmentAssignment $assignment): void
    {
        $process = $assignment->process()->with('assignments.version.form')->firstOrFail();
        $stage = $assignment->version->form->stage;

        if ($assignment->version->form->code === 'FR.APL.02') {
            $this->assignStageForms($process, 'pra_asesmen');
            $process->refresh()->load('assignments.version.form');
        }

        $stageAssignments = $process->assignments->filter(
            fn ($item) => $item->version->form->stage === $stage && $item->version->form->code !== 'FR.AK.04'
        );
        if ($stageAssignments->isEmpty() || $stageAssignments->contains(fn ($item) => !in_array($item->status, ['completed', 'assessed', 'result_published']))) {
            return;
        }

        if ($stage === 'pra_asesmen') {
            $this->assignStageForms($process, 'asesmen');
        } elseif ($stage === 'asesmen') {
            $this->assignStageForms($process, 'pasca_asesmen');
        } elseif ($stage === 'pasca_asesmen') {
            $process->update(['current_stage' => 'keputusan']);
        }
    }
}
