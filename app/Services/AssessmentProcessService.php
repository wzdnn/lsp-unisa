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

        $version = AssessmentFormVersion::with('form')
            ->where('status', 'published')
            ->whereHas('form', fn ($query) => $query->where('code', 'FR.APL.02')->where('filled_by', 'asesi'))
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
}
