<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentForm;
use App\Models\AssessmentFormVersion;
use App\Models\LspSkemaUnitKompetensi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssessmentFormController extends Controller
{
    private const QUESTION_TYPES = [
        'short_text', 'long_text', 'number', 'date', 'radio', 'checkbox',
        'select', 'file_upload', 'self_assessment', 'assessor_observation',
        'oral_question', 'practice_task', 'information', 'signature',
    ];

    public function index(Request $request)
    {
        $this->authorizeAdmin();

        return AssessmentForm::with(['scheme', 'programs', 'versions' => fn ($q) => $q->orderByDesc('version')])
            ->when($request->stage, fn ($q, $stage) => $q->where('stage', $stage))
            ->orderBy('stage')->orderBy('code')->get();
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();
        $data = $this->validatePayload($request);

        $form = DB::transaction(function () use ($data) {
            $form = AssessmentForm::create($data['form']);
            $form->programs()->sync($data['program_ids']);
            $version = $form->versions()->create(['version' => 1, 'status' => 'draft', 'settings' => $data['settings'] ?? []]);
            $this->replaceSections($version, $data['sections']);
            return $form;
        });

        return response()->json($form->load('versions.sections.questions.units'), 201);
    }

    public function show(AssessmentFormVersion $assessmentFormVersion)
    {
        $this->authorizeAdmin();
        return $assessmentFormVersion->load('form.programs', 'sections.questions.units');
    }

    public function update(Request $request, AssessmentFormVersion $assessmentFormVersion)
    {
        $this->authorizeAdmin();
        abort_if($assessmentFormVersion->status !== 'draft', 422, 'Versi yang sudah dipublikasikan tidak dapat diubah');
        $data = $this->validatePayload($request, $assessmentFormVersion->form_id);

        DB::transaction(function () use ($assessmentFormVersion, $data) {
            $assessmentFormVersion->form->update($data['form']);
            $assessmentFormVersion->form->programs()->sync($data['program_ids']);
            $assessmentFormVersion->update(['settings' => $data['settings'] ?? []]);
            $this->replaceSections($assessmentFormVersion, $data['sections']);
        });

        return $assessmentFormVersion->fresh()->load('form.programs', 'sections.questions.units');
    }

    public function publish(AssessmentFormVersion $assessmentFormVersion)
    {
        $this->authorizeAdmin();
        abort_if($assessmentFormVersion->status !== 'draft', 422, 'Hanya versi draft yang dapat dipublikasikan');
        abort_if(!$assessmentFormVersion->sections()->whereHas('questions')->exists(), 422, 'Form harus memiliki pertanyaan');

        DB::transaction(function () use ($assessmentFormVersion) {
            AssessmentFormVersion::where('form_id', $assessmentFormVersion->form_id)
                ->where('status', 'published')->update(['status' => 'archived']);
            $assessmentFormVersion->update(['status' => 'published', 'published_at' => now()]);
        });

        return $assessmentFormVersion->fresh()->load('form', 'sections.questions');
    }

    public function duplicate(AssessmentFormVersion $assessmentFormVersion)
    {
        $this->authorizeAdmin();
        $assessmentFormVersion->load('sections.questions.units');

        $copy = DB::transaction(function () use ($assessmentFormVersion) {
            $next = AssessmentFormVersion::where('form_id', $assessmentFormVersion->form_id)->max('version') + 1;
            $copy = AssessmentFormVersion::create([
                'form_id' => $assessmentFormVersion->form_id,
                'version' => $next,
                'status' => 'draft',
                'settings' => $assessmentFormVersion->settings,
            ]);
            foreach ($assessmentFormVersion->sections as $section) {
                $newSection = $copy->sections()->create($section->only('title', 'description', 'sort_order'));
                foreach ($section->questions as $question) {
                    $newQuestion = $newSection->questions()->create($question->only(
                        'code', 'type', 'label', 'instructions', 'is_required', 'sort_order',
                        'kdlsp_skema_unitkompetensi', 'kdlsp_skema_unitkompetensi_elemen',
                        'kdlsp_skema_unitkompetensi_elemen_kriteria', 'options', 'settings'
                    ));
                    $newQuestion->units()->attach($question->units->mapWithKeys(fn ($unit) => [
                        $unit->kdlsp_skema_unitkompetensi => [
                            'kdlsp_skema_unitkompetensi_elemen' => $unit->pivot->kdlsp_skema_unitkompetensi_elemen,
                            'kdlsp_skema_unitkompetensi_elemen_kriteria' => $unit->pivot->kdlsp_skema_unitkompetensi_elemen_kriteria,
                        ],
                    ])->all());
                }
            }
            return $copy;
        });

        return response()->json($copy->load('form', 'sections.questions'), 201);
    }

    private function validatePayload(Request $request, ?int $formId = null): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('lsp_assessment_forms', 'code')->ignore($formId)],
            'kdlsp_skema' => 'required|integer|exists:lsp_skema,kdlsp_skema',
            'program_ids' => 'required|array|min:1',
            'program_ids.*' => 'integer|exists:pt_unitkerja,kdunitkerja',
            'name' => 'required|string|max:255',
            'stage' => 'required|in:pra_asesmen,asesmen,pasca_asesmen',
            'filled_by' => 'required|in:asesi,asesor,bersama,admin',
            'reviewed_by' => 'nullable|in:asesor,admin,lead_asesor',
            'description' => 'nullable|string',
            'settings' => 'nullable|array',
            'sections' => 'required|array|min:1',
            'sections.*.title' => 'required|string|max:255',
            'sections.*.description' => 'nullable|string',
            'sections.*.questions' => 'required|array|min:1',
            'sections.*.questions.*.code' => 'required|string|max:80',
            'sections.*.questions.*.type' => ['required', Rule::in(self::QUESTION_TYPES)],
            'sections.*.questions.*.label' => 'required|string',
            'sections.*.questions.*.instructions' => 'nullable|string',
            'sections.*.questions.*.is_required' => 'sometimes|boolean',
            'sections.*.questions.*.options' => 'nullable|array',
            'sections.*.questions.*.settings' => 'nullable|array',
            'sections.*.questions.*.unit_ids' => 'nullable|array',
            'sections.*.questions.*.unit_ids.*' => 'integer|distinct|exists:lsp_skema_unitkompetensi,kdlsp_skema_unitkompetensi',
            'sections.*.questions.*.kdlsp_skema_unitkompetensi' => 'nullable|integer',
            'sections.*.questions.*.kdlsp_skema_unitkompetensi_elemen' => 'nullable|integer',
            'sections.*.questions.*.kdlsp_skema_unitkompetensi_elemen_kriteria' => 'nullable|integer',
        ]);

        $unitIds = collect($validated['sections'])->flatMap(fn ($section) => collect($section['questions'])
            ->flatMap(fn ($question) => $question['unit_ids'] ?? []))->unique()->values();
        $invalidUnitExists = $unitIds->isNotEmpty() && LspSkemaUnitKompetensi::whereIn('kdlsp_skema_unitkompetensi', $unitIds)
            ->where('kdlsp_skema', '<>', $validated['kdlsp_skema'])->exists();
        abort_if($invalidUnitExists, 422, 'Unit kompetensi harus berasal dari skema form yang dipilih');

        return [
            'form' => collect($validated)->only('code', 'kdlsp_skema', 'name', 'stage', 'filled_by', 'reviewed_by', 'description')->all(),
            'program_ids' => $validated['program_ids'],
            'settings' => $validated['settings'] ?? [],
            'sections' => $validated['sections'],
        ];
    }

    private function replaceSections(AssessmentFormVersion $version, array $sections): void
    {
        $version->sections()->delete();
        foreach ($sections as $sectionIndex => $section) {
            $model = $version->sections()->create([
                'title' => $section['title'],
                'description' => $section['description'] ?? null,
                'sort_order' => $sectionIndex,
            ]);
            foreach ($section['questions'] as $questionIndex => $question) {
                $unitIds = $question['unit_ids'] ?? [];
                unset($question['unit_ids']);
                if ($unitIds && empty($question['kdlsp_skema_unitkompetensi'])) {
                    $question['kdlsp_skema_unitkompetensi'] = $unitIds[0];
                }
                $questionModel = $model->questions()->create(array_merge($question, ['sort_order' => $questionIndex]));
                $questionModel->units()->sync($unitIds);
            }
        }
    }

    private function authorizeAdmin(): void
    {
        abort_unless(in_array(session('user.role'), ['admin', 'superadmin', 'tendik']), 403);
    }
}
