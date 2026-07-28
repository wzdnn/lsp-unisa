<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LspApl01Pengajuan;
use App\Models\LspUser;
use Illuminate\Http\Request;

class LspApl01PengajuanController extends Controller
{
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
            'dokumen'
        ])
            ->where('kdlsp_user', $this->currentLspUser()->kdlsp_user)
            ->where('kdlsp_periode_skema', $request->kdlsp_periode_skema)
            ->first();

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

        $pengajuan = LspApl01Pengajuan::firstOrNew([
            'kdlsp_user' => $user->kdlsp_user,
            'kdlsp_periode_skema' => $validated['kdlsp_periode_skema'],
        ]);

        if (in_array($pengajuan->status, ['menunggu_review', 'diterima', 'ditolak'])) {
            return response()->json([
                'message' => 'Pengajuan yang sedang atau sudah direview tidak dapat diubah',
            ], 422);
        }

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

        return response()->json(
            $pengajuan->load([
                'periodeSkema.periode',
                'periodeSkema.masaPeriode',
                'periodeSkema.skema',
                'dokumen',
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
        ])->findOrFail($kdlsp_apl01_pengajuan);

        if (
            session('user.role') === 'mahasiswa' &&
            $pengajuan->kdlsp_user !== $this->currentLspUser()->kdlsp_user
        ) {
            abort(403, 'Pengajuan ini bukan milik Anda');
        }

        return response()->json($pengajuan);
    }

    public function review(Request $request, $kdlsp_apl01_pengajuan)
    {
        $this->authorizeAdminRole();

        $validated = $request->validate([
            'status' => 'required|in:diterima,perlu_revisi,ditolak',
            'catatan_admin' => 'nullable|string',
        ]);

        $pengajuan = LspApl01Pengajuan::findOrFail($kdlsp_apl01_pengajuan);
        $reviewer = $this->currentLspUser(false);

        $pengajuan->update([
            'status' => $validated['status'],
            'catatan_admin' => $validated['catatan_admin'] ?? null,
            'reviewed_by' => $reviewer?->kdlsp_user,
            'reviewed_at' => now(),
        ]);

        return response()->json($pengajuan->load(['user.person', 'reviewer.person']));
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

    private function authorizeAdminRole(): void
    {
        $role = session('user.role');

        if (!in_array($role, ['admin', 'superadmin', 'tendik'])) {
            abort(403, 'Hanya admin yang dapat melakukan aksi ini');
        }
    }
}
