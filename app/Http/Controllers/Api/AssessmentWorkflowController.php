<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentDecision;
use App\Models\AssessmentEvidence;
use App\Models\AssessmentFormVersion;
use App\Models\AssessmentProcess;
use App\Models\AssessmentReview;
use App\Models\LspUser;
use App\Models\LspUserSignature;
use App\Models\LspPeriodeSkema;
use App\Services\AssessmentProcessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AssessmentWorkflowController extends Controller
{
    public function processes(Request $request)
    {
        $this->authorizeAdmin();

        return AssessmentProcess::with([
            'asesi.person', 'assessor.person', 'periodeSkema.skema',
            'periodeSkema.periode', 'assignments.version.form',
        ])
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->get();
    }

    public function assignApl02(Request $request, AssessmentProcess $assessmentProcess, AssessmentProcessService $service)
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'assessor_id' => 'required|integer|exists:lsp_user,kdlsp_user',
            'due_at' => 'nullable|date',
        ]);

        $assessor = LspUser::findOrFail($data['assessor_id']);
        $assignment = $service->assignAssessorAndApl02($assessmentProcess, $assessor, $data['due_at'] ?? null);

        return response()->json($assignment->load($this->relations()), 201);
    }

    public function index(Request $request)
    {
        $user = $this->currentUser();
        $role = session('user.role');
        $query = AssessmentAssignment::with($this->relations())->latest();

        if ($role === 'mahasiswa') {
            $query->where('assigned_to', $user->kdlsp_user)->where('assignee_role', 'asesi');
        } elseif (in_array($role, ['dosen', 'asesor_luar'])) {
            $query->whereHas('process', fn ($q) => $q->where('assessor_id', $user->kdlsp_user));
            if ($request->filled('process_id')) {
                $query->where('process_id', $request->integer('process_id'));
            }
        } else {
            $this->authorizeAdmin();
        }

        return $query->get();
    }

    public function assign(Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'kdlsp_periode_skema' => 'required|integer|exists:lsp_periode_skema,kdlsp_periode_skema',
            'asesi_id' => 'required|integer|exists:lsp_user,kdlsp_user',
            'assessor_id' => 'nullable|integer|exists:lsp_user,kdlsp_user',
            'kdlsp_apl01_pengajuan' => 'nullable|integer',
            'form_version_ids' => 'required|array|min:1',
            'form_version_ids.*' => 'integer|exists:lsp_assessment_form_versions,id',
            'due_at' => 'nullable|date',
        ]);

        $versions = AssessmentFormVersion::with('form.programs')->whereIn('id', $data['form_version_ids'])
            ->where('status', 'published')->get();
        abort_if($versions->count() !== count(array_unique($data['form_version_ids'])), 422, 'Semua form harus berstatus published');

        $schemeId = LspPeriodeSkema::findOrFail($data['kdlsp_periode_skema'])->kdlsp_skema;
        $asesi = LspUser::findOrFail($data['asesi_id']);
        foreach ($versions as $version) {
            abort_if((int) $version->form->kdlsp_skema !== (int) $schemeId, 422, "Form {$version->form->code} tidak sesuai skema periode");
            abort_if(!$asesi->kdunit || !$version->form->programs->contains('kdunitkerja', $asesi->kdunit), 422, "Form {$version->form->code} tidak tersedia untuk program studi asesi");
        }

        $process = DB::transaction(function () use ($data, $versions) {
            $process = AssessmentProcess::firstOrCreate([
                'kdlsp_periode_skema' => $data['kdlsp_periode_skema'],
                'asesi_id' => $data['asesi_id'],
                'status' => 'active',
            ], [
                'assessor_id' => $data['assessor_id'] ?? null,
                'kdlsp_apl01_pengajuan' => $data['kdlsp_apl01_pengajuan'] ?? null,
                'current_stage' => 'pra_asesmen',
                'started_at' => now(),
            ]);
            $process->update(['assessor_id' => $data['assessor_id'] ?? $process->assessor_id]);

            foreach ($versions as $version) {
                $forAsesi = in_array($version->form->filled_by, ['asesi', 'bersama']);
                $assignedTo = $forAsesi ? $data['asesi_id'] : ($data['assessor_id'] ?? null);
                abort_if(!$assignedTo, 422, "Form {$version->form->code} membutuhkan asesor");
                AssessmentAssignment::firstOrCreate([
                    'process_id' => $process->id,
                    'form_version_id' => $version->id,
                    'assigned_to' => $assignedTo,
                ], [
                    'assignee_role' => $forAsesi ? 'asesi' : 'asesor',
                    'due_at' => $data['due_at'] ?? null,
                ]);
            }
            return $process;
        });

        return response()->json($process->load('assignments.version.form'), 201);
    }

    public function show(AssessmentAssignment $assessmentAssignment)
    {
        $this->authorizeView($assessmentAssignment);
        return $assessmentAssignment->load($this->relations());
    }

    public function saveAnswers(Request $request, AssessmentAssignment $assessmentAssignment)
    {
        $this->authorizeEditor($assessmentAssignment);
        abort_unless($this->answersAreEditable($assessmentAssignment), 422, 'Jawaban sudah dikunci');
        $data = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer',
            'answers.*.answer_text' => 'nullable|string',
            'answers.*.answer_json' => 'nullable',
        ]);
        $questionIds = $assessmentAssignment->version->sections()->with('questions')->get()
            ->flatMap->questions->pluck('id');

        DB::transaction(function () use ($data, $assessmentAssignment, $questionIds) {
            foreach ($data['answers'] as $answer) {
                abort_unless($questionIds->contains($answer['question_id']), 422, 'Pertanyaan tidak termasuk form ini');
                AssessmentAnswer::updateOrCreate(
                    ['assignment_id' => $assessmentAssignment->id, 'question_id' => $answer['question_id']],
                    ['answer_text' => $answer['answer_text'] ?? null, 'answer_json' => $answer['answer_json'] ?? null]
                );
            }
            $assessmentAssignment->update(['status' => $assessmentAssignment->status === 'under_review' ? 'under_review' : 'draft']);
        });

        return $assessmentAssignment->fresh()->load($this->relations());
    }

    public function uploadEvidence(Request $request, AssessmentAssignment $assessmentAssignment)
    {
        $this->authorizeEditor($assessmentAssignment);
        abort_unless($this->answersAreEditable($assessmentAssignment), 422, 'Jawaban sudah dikunci');
        $data = $request->validate(['question_id' => 'required|integer', 'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx']);
        $questionIds = $assessmentAssignment->version->sections()->with('questions')->get()->flatMap->questions->pluck('id');
        abort_unless($questionIds->contains($data['question_id']), 422, 'Pertanyaan tidak termasuk form ini');
        $answer = AssessmentAnswer::firstOrCreate(['assignment_id' => $assessmentAssignment->id, 'question_id' => $data['question_id']]);
        $file = $request->file('file');
        $path = $file->store("assessment/{$assessmentAssignment->id}", 'public');
        $evidence = AssessmentEvidence::create([
            'answer_id' => $answer->id, 'disk' => 'public', 'path' => $path,
            'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
        ]);
        return response()->json($evidence, 201);
    }

    public function deleteEvidence(AssessmentAssignment $assessmentAssignment, AssessmentEvidence $assessmentEvidence)
    {
        $this->authorizeOwner($assessmentAssignment);
        abort_unless($assessmentEvidence->answer?->assignment_id === $assessmentAssignment->id, 404);
        Storage::disk($assessmentEvidence->disk)->delete($assessmentEvidence->path);
        $assessmentEvidence->delete();
        return response()->json(['message' => 'Bukti dihapus']);
    }

    public function submit(AssessmentAssignment $assessmentAssignment)
    {
        $this->authorizeEditor($assessmentAssignment);
        abort_unless($this->answersAreEditable($assessmentAssignment), 422, 'Form tidak dapat dikirim');
        $actorRole = session('user.role') === 'mahasiswa' ? 'asesi' : 'asesor';
        $required = $assessmentAssignment->version->sections()->with('questions')->get()->flatMap->questions
            ->where('is_required', true)
            ->filter(fn ($question) => $question->type !== 'signature' || ($question->settings['signer_role'] ?? $actorRole) === $actorRole)
            ->pluck('id');
        $answered = $assessmentAssignment->answers()->where(function ($q) {
            $q->whereNotNull('answer_text')->orWhereNotNull('answer_json')->orWhereHas('evidences');
        })->pluck('question_id');
        abort_if($required->diff($answered)->isNotEmpty(), 422, 'Masih ada pertanyaan wajib yang belum dijawab');
        $assessorOwned = $assessmentAssignment->version->form->filled_by === 'asesor';
        $assessmentAssignment->update([
            'status' => $assessorOwned ? 'completed' : 'submitted',
            'submitted_at' => now(),
            'completed_at' => $assessorOwned ? now() : null,
        ]);
        if ($assessorOwned) {
            app(AssessmentProcessService::class)->syncAfterCompletion($assessmentAssignment);
        }
        return $assessmentAssignment->fresh()->load($this->relations());
    }

    public function sign(AssessmentAssignment $assessmentAssignment, Request $request)
    {
        $user = $this->authorizeEditor($assessmentAssignment);
        $sharedAssessorSigning = $assessmentAssignment->version->form->filled_by === 'bersama'
            && in_array(session('user.role'), ['dosen', 'asesor_luar'])
            && $assessmentAssignment->status === 'submitted';
        abort_unless($this->answersAreEditable($assessmentAssignment) || $sharedAssessorSigning, 422, 'Form sudah dikunci');
        $data = $request->validate(['question_id' => 'required|integer']);
        $question = $assessmentAssignment->version->sections()->with('questions')->get()
            ->flatMap->questions->firstWhere('id', $data['question_id']);
        abort_unless($question && $question->type === 'signature', 422, 'Pertanyaan bukan kolom tanda tangan');
        $actorRole = session('user.role') === 'mahasiswa' ? 'asesi' : 'asesor';
        abort_unless(($question->settings['signer_role'] ?? $actorRole) === $actorRole, 403, 'Kolom tanda tangan ini bukan untuk peran Anda');

        $signature = LspUserSignature::where('kdlsp_user', $user->kdlsp_user)
            ->where('is_active', true)->latest('kdlsp_user_signature')->first();
        abort_unless($signature, 422, 'Simpan tanda tangan aktif pada profil terlebih dahulu');

        AssessmentAnswer::updateOrCreate(
            ['assignment_id' => $assessmentAssignment->id, 'question_id' => $question->id],
            ['answer_text' => null, 'answer_json' => [
                'signed' => true,
                'signer_id' => $user->kdlsp_user,
                'signer_role' => session('user.role'),
                'signature_id' => $signature->kdlsp_user_signature,
                'signed_at' => now()->toIso8601String(),
            ]]
        );
        if (!$sharedAssessorSigning) {
            $assessmentAssignment->update(['status' => 'draft']);
        }

        return $assessmentAssignment->fresh()->load($this->relations());
    }

    public function review(Request $request, AssessmentAssignment $assessmentAssignment)
    {
        $assessor = $this->authorizeAssessor($assessmentAssignment);
        abort_unless(in_array($assessmentAssignment->status, ['submitted', 'under_review', 'assessed']), 422, 'Jawaban belum disubmit');
        $data = $request->validate([
            'reviews' => 'required|array|min:1',
            'reviews.*.question_id' => 'required|integer',
            'reviews.*.result' => 'required|in:achieved,not_achieved,needs_follow_up,not_assessed',
            'reviews.*.notes' => 'nullable|string',
        ]);
        $questionIds = $assessmentAssignment->version->sections()->with('questions')->get()
            ->flatMap->questions->pluck('id');
        foreach ($data['reviews'] as $review) {
            abort_unless($questionIds->contains($review['question_id']), 422, 'Pertanyaan tidak termasuk form ini');
            AssessmentReview::updateOrCreate(
                ['assignment_id' => $assessmentAssignment->id, 'question_id' => $review['question_id'], 'assessor_id' => $assessor->kdlsp_user],
                ['result' => $review['result'], 'notes' => $review['notes'] ?? null, 'reviewed_at' => now()]
            );
        }
        $assessmentAssignment->update(['status' => 'under_review']);
        return $assessmentAssignment->fresh()->load($this->relations());
    }

    public function requestRevision(Request $request, AssessmentAssignment $assessmentAssignment)
    {
        $this->authorizeAssessor($assessmentAssignment);
        abort_unless(in_array($assessmentAssignment->status, ['submitted', 'under_review']), 422, 'Form tidak dapat dikembalikan untuk revisi');
        $data = $request->validate(['notes' => 'required|string|max:5000']);

        $assessmentAssignment->update([
            'status' => 'revision_required',
            'revision_notes' => $data['notes'],
            'revision_requested_at' => now(),
            'reviewed_at' => now(),
        ]);

        return $assessmentAssignment->fresh()->load($this->relations());
    }

    public function completeReview(AssessmentAssignment $assessmentAssignment, AssessmentProcessService $service)
    {
        $this->authorizeAssessor($assessmentAssignment);
        abort_unless(in_array($assessmentAssignment->status, ['submitted', 'under_review']), 422, 'Review tidak dapat diselesaikan');

        $reviewableIds = $assessmentAssignment->version->sections()->with('questions')->get()
            ->flatMap->questions->where('type', '!=', 'information')->pluck('id');
        $reviewedIds = $assessmentAssignment->reviews()
            ->where('result', '!=', 'not_assessed')->pluck('question_id');

        abort_if($reviewableIds->diff($reviewedIds)->isNotEmpty(), 422, 'Semua pertanyaan harus dinilai sebelum review diselesaikan');

        DB::transaction(function () use ($assessmentAssignment) {
            $assessmentAssignment->update([
                'status' => 'completed',
                'reviewed_at' => now(),
                'completed_at' => now(),
                'revision_notes' => null,
            ]);
        });
        $service->syncAfterCompletion($assessmentAssignment);

        return $assessmentAssignment->fresh()->load($this->relations());
    }

    public function decide(Request $request, AssessmentAssignment $assessmentAssignment)
    {
        $assessor = $this->authorizeAssessor($assessmentAssignment);
        abort_unless(in_array($assessmentAssignment->status, ['submitted', 'under_review', 'assessed']), 422, 'Assessment belum siap diputuskan');
        $data = $request->validate(['result' => 'required|in:competent,not_competent', 'notes' => 'nullable|string', 'publish' => 'sometimes|boolean']);
        $decision = AssessmentDecision::updateOrCreate(['assignment_id' => $assessmentAssignment->id], [
            'assessor_id' => $assessor->kdlsp_user, 'result' => $data['result'], 'notes' => $data['notes'] ?? null,
            'is_published' => $request->boolean('publish'), 'decided_at' => now(),
            'published_at' => $request->boolean('publish') ? now() : null,
        ]);
        $assessmentAssignment->update(['status' => $decision->is_published ? 'result_published' : 'assessed', 'reviewed_at' => now()]);
        return $assessmentAssignment->fresh()->load($this->relations());
    }

    public function decideProcess(Request $request, AssessmentProcess $assessmentProcess)
    {
        abort_unless(in_array(session('user.role'), ['dosen', 'asesor_luar']), 403);
        $assessor = $this->currentUser();
        abort_unless($assessmentProcess->assessor_id === $assessor->kdlsp_user, 403);
        abort_unless($assessmentProcess->current_stage === 'keputusan', 422, 'Seluruh form wajib belum selesai');
        $data = $request->validate([
            'result' => 'required|in:competent,not_competent',
            'notes' => 'nullable|string|max:10000',
            'publish' => 'sometimes|boolean',
        ]);

        $assessmentProcess->update([
            'final_result' => $data['result'],
            'decision_notes' => $data['notes'] ?? null,
            'decided_by' => $assessor->kdlsp_user,
            'decided_at' => now(),
            'result_published_at' => $request->boolean('publish') ? now() : null,
            'status' => $request->boolean('publish') ? 'completed' : 'active',
            'completed_at' => $request->boolean('publish') ? now() : null,
        ]);

        return $assessmentProcess->fresh()->load('assignments.version.form');
    }

    private function relations(): array
    {
        return ['version.form', 'version.sections.questions', 'process.asesi.person', 'process.assessor.person',
            'process.periodeSkema.skema', 'answers.evidences', 'answers.question', 'reviews', 'decision'];
    }

    private function currentUser(): LspUser
    {
        return LspUser::where('username', session('user.username'))->firstOrFail();
    }

    private function authorizeView(AssessmentAssignment $assignment): void
    {
        $role = session('user.role');
        if (in_array($role, ['admin', 'superadmin', 'tendik'])) return;
        $user = $this->currentUser();
        abort_unless($assignment->assigned_to === $user->kdlsp_user || $assignment->process->assessor_id === $user->kdlsp_user, 403);
    }

    private function authorizeOwner(AssessmentAssignment $assignment): void
    {
        abort_unless($assignment->assigned_to === $this->currentUser()->kdlsp_user, 403);
    }

    private function authorizeEditor(AssessmentAssignment $assignment): LspUser
    {
        $user = $this->currentUser();
        $filledBy = $assignment->version->form->filled_by;
        $isOwner = $assignment->assigned_to === $user->kdlsp_user;
        $isProcessAssessor = $assignment->process->assessor_id === $user->kdlsp_user;

        abort_unless(
            ($isOwner && in_array($filledBy, ['asesi', 'asesor', 'bersama'])) ||
            ($isProcessAssessor && in_array($filledBy, ['asesor', 'bersama'])),
            403
        );

        return $user;
    }

    private function answersAreEditable(AssessmentAssignment $assignment): bool
    {
        return in_array($assignment->status, ['assigned', 'draft', 'revision_required'])
            || ($assignment->status === 'under_review' && $assignment->version->form->filled_by === 'asesor');
    }

    private function authorizeAssessor(AssessmentAssignment $assignment): LspUser
    {
        abort_unless(in_array(session('user.role'), ['dosen', 'asesor_luar']), 403);
        $user = $this->currentUser();
        abort_unless($assignment->process->assessor_id === $user->kdlsp_user, 403);
        return $user;
    }

    private function authorizeAdmin(): void
    {
        abort_unless(in_array(session('user.role'), ['admin', 'superadmin', 'tendik']), 403);
    }
}
