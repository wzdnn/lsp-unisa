<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LspApl01Pengajuan;
use App\Models\LspDocumentSignature;
use App\Models\LspUser;
use App\Models\LspUserSignature;
use App\Models\LspPeriodeSkema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\ChecksLspPayment;
use App\Services\AssessmentProcessService;

class LspApl01PengajuanController extends Controller
{
    use ChecksLspPayment;

    private const APL01_SUBMIT_CONSENT = 'Saya menyatakan bahwa data FR.APL.01 yang saya isi adalah benar dan saya menyetujui penggunaan tanda tangan elektronik saya untuk permohonan sertifikasi kompetensi ini.';

    public function index(Request $request)
    {
        if (session('user.role') !== 'mahasiswa') {
            $this->authorizeAdminRole();
        }

        $query = LspApl01Pengajuan::with([
            'user.person',
            'periodeSkema.periode',
            'periodeSkema.masaPeriode',
            'periodeSkema.skema',
            'reviewer.person',
        ])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if (session('user.role') === 'mahasiswa') {
            $query->where('kdlsp_user', $this->currentLspUser()->kdlsp_user);
        }

        return response()->json($query->get());
    }

    public function current(Request $request)
    {
        $request->validate([
            'kdlsp_periode_skema' => 'required|exists:lsp_periode_skema,kdlsp_periode_skema',
        ]);

        $pengajuan = LspApl01Pengajuan::with([
            'periodeSkema.periode',
            'periodeSkema.masaPeriode',
            'periodeSkema.skema',
            'dokumen',
            'documentSignatures',
        ])
            ->where('kdlsp_user', $this->currentLspUser()->kdlsp_user)
            ->where('kdlsp_periode_skema', $request->kdlsp_periode_skema)
            ->first();

        if ($pengajuan) {
            $pengajuan->sudah_bayar = $this->cekPembayaran($pengajuan->kdlsp_apl01_pengajuan);
            $pengajuan->batas_pembayaran = $this->batasPembayaranTagihan($pengajuan->kdlsp_apl01_pengajuan);
        }

        return response()->json($pengajuan);
    }

    public function store(Request $request)
    {
        $role = session('user.role');

        if ($role !== 'mahasiswa') {
            abort(403, 'Hanya mahasiswa yang dapat membuat pengajuan');
        }

        $validated = $request->validate([
            'kdlsp_periode_skema' => 'required|exists:lsp_periode_skema,kdlsp_periode_skema',
            'data_pribadi' => 'required|array',
            'data_pekerjaan' => 'required|array',
            'data_sertifikasi' => 'required|array',
            'data_persyaratan' => 'nullable|array',
            'submit' => 'sometimes|boolean',
        ]);

        $status = $request->boolean('submit') ? 'menunggu_review' : 'draft';
        $user = $this->currentLspUser();
        $activeSignature = null;

        if ($status === 'menunggu_review') {
            $activeSignature = $this->activeSignatureFor($user);

            if (!$activeSignature) {
                return response()->json([
                    'message' => 'Tanda tangan pemohon wajib disimpan sebelum mengirim FR.APL.01',
                ], 422);
            }
        }

        $pengajuan = LspApl01Pengajuan::firstOrNew([
            'kdlsp_user' => $user->kdlsp_user,
            'kdlsp_periode_skema' => $validated['kdlsp_periode_skema'],
        ]);

        if (!$pengajuan->exists) {
            return response()->json([
                'message' => 'Pengajuan belum dimulai, silakan pilih skema kembali',
            ], 422);
        }

        if (!$this->cekPembayaran($pengajuan->kdlsp_apl01_pengajuan)) {
            return response()->json([
                'message' => 'Pembayaran belum diterima, data tidak dapat disimpan',
            ], 422);
        }

        if (in_array($pengajuan->status, ['menunggu_review', 'diterima', 'ditolak'])) {
            return response()->json([
                'message' => 'Pengajuan yang sedang atau sudah direview tidak dapat diubah',
            ], 422);
        }

        DB::transaction(function () use ($request, $validated, $status, $user, $activeSignature, $pengajuan) {
            $pengajuan->fill([
                'data_pribadi' => $validated['data_pribadi'],
                'data_pekerjaan' => $validated['data_pekerjaan'],
                'data_sertifikasi' => $validated['data_sertifikasi'],
                'data_persyaratan' => $validated['data_persyaratan']
                                    ?? $pengajuan->data_persyaratan
                                    ?? [],
                'status' => $status,
                'submitted_at' => $status === 'menunggu_review'
                    ? now()
                    : null,
            ]);

            $pengajuan->save();

            if ($status === 'menunggu_review') {
                $this->recordApl01ApplicantSignature(
                    $request,
                    $pengajuan,
                    $user,
                    $activeSignature,
                );
            }
        });

        return response()->json(
            $pengajuan->load([
                'periodeSkema.periode',
                'periodeSkema.masaPeriode',
                'periodeSkema.skema',
                'dokumen',
                'documentSignatures',
            ]),
            $pengajuan->wasRecentlyCreated ? 201 : 200
        );
    }

    public function show($kdlsp_apl01_pengajuan)
    {
        $pengajuan = LspApl01Pengajuan::with([
            'user.person',
            'periodeSkema.periode',
            'periodeSkema.masaPeriode',
            'periodeSkema.skema',
            'reviewer.person',
            'dokumen',
            'documentSignatures',
        ])->findOrFail($kdlsp_apl01_pengajuan);

        $this->authorizeView($pengajuan);

        return response()->json($pengajuan);
    }

    public function review(Request $request, $kdlsp_apl01_pengajuan, AssessmentProcessService $assessmentProcessService)
    {
        $this->authorizeAdminRole();

        $validated = $request->validate([
            'status' => 'required|in:diterima,perlu_revisi,ditolak',
            'catatan_admin' => 'nullable|string',
        ]);

        $pengajuan = LspApl01Pengajuan::findOrFail($kdlsp_apl01_pengajuan);
        $reviewer = $this->currentLspUser(false);

        DB::transaction(function () use ($pengajuan, $validated, $reviewer, $assessmentProcessService) {
            $pengajuan->update([
                'status' => $validated['status'],
                'catatan_admin' => $validated['catatan_admin'] ?? null,
                'reviewed_by' => $reviewer?->kdlsp_user,
                'reviewed_at' => now(),
            ]);

            if ($validated['status'] === 'diterima') {
                $assessmentProcessService->startFromAcceptedApl01($pengajuan);
            }
        });

        return response()->json($pengajuan->load(['user.person', 'reviewer.person', 'assessmentProcess']));
    }

    public function mulai(Request $request)
    {
        if (session('user.role') !== 'mahasiswa') {
            abort(403, 'Hanya mahasiswa yang dapat mendaftar skema');
        }

        $request->validate([
            'kdlsp_periode_skema' => 'required|exists:lsp_periode_skema,kdlsp_periode_skema',
        ]);

        $this->validateRegistrationWindow((int) $request->kdlsp_periode_skema);

        $user = $this->currentLspUser();

        try {
            $pengajuan = LspApl01Pengajuan::firstOrCreate(
                [
                    'kdlsp_user' => $user->kdlsp_user,
                    'kdlsp_periode_skema' => $request->kdlsp_periode_skema,
                ],
                [
                    'data_pribadi' => [],
                    'data_pekerjaan' => [],
                    'data_sertifikasi' => [],
                    'data_persyaratan' => [],
                    'status' => 'draft',
                ]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'Nominal tarif')) {
                return response()->json([
                    'message' => 'Skema ini belum tersedia untuk pendaftaran. Silakan hubungi admin.',
                ], 422);
            }
            throw $e;
        }

        $pengajuan->load(['periodeSkema.periode', 'periodeSkema.masaPeriode', 'periodeSkema.skema', 'dokumen', 'documentSignatures']);
        $pengajuan->sudah_bayar = $this->cekPembayaran($pengajuan->kdlsp_apl01_pengajuan);
        $pengajuan->batas_pembayaran = $this->batasPembayaranTagihan($pengajuan->kdlsp_apl01_pengajuan);

        return response()->json($pengajuan);
    }

    private function currentLspUser(bool $required = true): ?LspUser
    {
        $username = session('user.username');

        $user = $username
            ? LspUser::where('username', $username)->first()
            : null;

        if (!$user && $required) {
            abort(422, 'Data user LSP tidak ditemukan');
        }

        return $user;
    }

    private function activeSignatureFor(LspUser $user): ?LspUserSignature
    {
        return LspUserSignature::where('kdlsp_user', $user->kdlsp_user)
            ->where('is_active', true)
            ->latest('kdlsp_user_signature')
            ->first();
    }

    private function validateRegistrationWindow(int $periodSchemeId): void
    {
        $plot = LspPeriodeSkema::with(['masaPeriode', 'skema'])->findOrFail($periodSchemeId);
        $masa = $plot->masaPeriode;

        abort_unless($plot->skema?->isActive, 422, 'Skema sertifikasi sudah tidak aktif');
        abort_unless($masa?->isActive, 422, 'Masa pendaftaran sudah tidak aktif');
        abort_if($masa->tanggal_mulai && today()->lt($masa->tanggal_mulai), 422, 'Masa pendaftaran belum dimulai');
        abort_if($masa->tanggal_selesai && today()->gt($masa->tanggal_selesai), 422, 'Masa pendaftaran sudah berakhir');
    }

    private function authorizeView(LspApl01Pengajuan $pengajuan): void
    {
        $role = session('user.role');
        if (in_array($role, ['admin', 'superadmin', 'tendik'])) {
            return;
        }

        $user = $this->currentLspUser();
        if ($role === 'mahasiswa') {
            abort_unless($pengajuan->kdlsp_user === $user->kdlsp_user, 403, 'Pengajuan ini bukan milik Anda');
            return;
        }

        if (in_array($role, ['dosen', 'asesor_luar'])) {
            abort_unless(
                $pengajuan->assessmentProcess()->where('assessor_id', $user->kdlsp_user)->exists(),
                403,
                'Anda bukan asesor yang ditugaskan untuk pengajuan ini'
            );
            return;
        }

        abort(403);
    }

    private function recordApl01ApplicantSignature(
        Request $request,
        LspApl01Pengajuan $pengajuan,
        LspUser $user,
        LspUserSignature $signature,
    ): void {
        LspDocumentSignature::where([
            'kdlsp_user' => $user->kdlsp_user,
            'module' => 'apl01',
            'stage' => 'pendaftaran',
            'document_type' => 'apl01_pengajuan',
            'document_id' => $pengajuan->kdlsp_apl01_pengajuan,
            'signature_purpose' => 'submit_pengajuan_asesi',
            'status' => 'signed',
        ])->update([
            'status' => 'voided',
            'revoked_at' => now(),
            'revoked_by' => $user->kdlsp_user,
            'revoked_reason' => 'Diganti oleh submit ulang FR.APL.01',
        ]);

        LspDocumentSignature::create([
            'kdlsp_user' => $user->kdlsp_user,
            'kdlsp_user_signature' => $signature->kdlsp_user_signature,
            'signer_role' => session('user.role') ?: 'mahasiswa',
            'module' => 'apl01',
            'stage' => 'pendaftaran',
            'document_type' => 'apl01_pengajuan',
            'document_id' => $pengajuan->kdlsp_apl01_pengajuan,
            'signature_purpose' => 'submit_pengajuan_asesi',
            'signature_label' => 'Tanda tangan asesi',
            'signature_file_path' => $signature->file_path,
            'signature_file_hash' => $signature->file_hash,
            'source_file_hash' => $this->apl01PayloadHash($pengajuan),
            'consent_text' => self::APL01_SUBMIT_CONSENT,
            'consent_hash' => hash('sha256', self::APL01_SUBMIT_CONSENT),
            'auth_method' => 'session',
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'signed_at' => now(),
            'status' => 'signed',
            'metadata' => [
                'kdlsp_periode_skema' => $pengajuan->kdlsp_periode_skema,
                'submitted_at' => optional($pengajuan->submitted_at)->toISOString(),
                'signature_type' => $signature->signature_type,
            ],
        ]);
    }

    private function apl01PayloadHash(LspApl01Pengajuan $pengajuan): string
    {
        return hash('sha256', json_encode([
            'kdlsp_apl01_pengajuan' => $pengajuan->kdlsp_apl01_pengajuan,
            'kdlsp_user' => $pengajuan->kdlsp_user,
            'kdlsp_periode_skema' => $pengajuan->kdlsp_periode_skema,
            'data_pribadi' => $pengajuan->data_pribadi,
            'data_pekerjaan' => $pengajuan->data_pekerjaan,
            'data_sertifikasi' => $pengajuan->data_sertifikasi,
            'data_persyaratan' => $pengajuan->data_persyaratan,
            'status' => $pengajuan->status,
            'submitted_at' => optional($pengajuan->submitted_at)->toISOString(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function authorizeAdminRole(): void
    {
        $role = session('user.role');

        if (!in_array($role, ['admin', 'superadmin', 'tendik'])) {
            abort(403, 'Hanya admin yang dapat melakukan aksi ini');
        }
    }
}
