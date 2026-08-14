<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LspApl01Dokumen;
use App\Models\LspApl01Pengajuan;
use App\Models\LspUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Traits\ChecksLspPayment;

class LspApl01DokumenController extends Controller
{

    use ChecksLspPayment;

    public function index($kdlsp_apl01_pengajuan)
    {
        $pengajuan = LspApl01Pengajuan::findOrFail($kdlsp_apl01_pengajuan);
        $this->authorizeAccess($pengajuan);

        return response()->json($pengajuan->dokumen);
    }

    public function store(Request $request, $kdlsp_apl01_pengajuan)
    {
        $pengajuan = LspApl01Pengajuan::findOrFail($kdlsp_apl01_pengajuan);
        $this->authorizeAccess($pengajuan, true);

        if (in_array($pengajuan->status, ['menunggu_review', 'diterima', 'ditolak'])) {
            return response()->json(['message' => 'Pengajuan tidak dapat diubah'], 422);
        }

        $request->validate([
            'jenis_dokumen' => 'required|string|max:50',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Hapus dokumen lama dengan jenis yang sama
        $old = $pengajuan->dokumen()
            ->where('jenis_dokumen', $request->jenis_dokumen)
            ->first();

        if ($old) {
            Storage::disk('public')->delete($old->file_path);
            $old->delete();
        }

        $path = $request->file('file')->store('apl01-dokumen', 'public');

        $dokumen = $pengajuan->dokumen()->create([
            'jenis_dokumen'  => $request->jenis_dokumen,
            'file_path'      => $path,
            'original_name'  => $request->file('file')->getClientOriginalName(),
        ]);

        return response()->json($dokumen, 201);
    }

    public function destroy($kdlsp_apl01_pengajuan, $kdlsp_apl01_dokumen)
    {
        $pengajuan = LspApl01Pengajuan::findOrFail($kdlsp_apl01_pengajuan);
        $this->authorizeAccess($pengajuan, true);

        $dokumen = LspApl01Dokumen::where('kdlsp_apl01_pengajuan', $kdlsp_apl01_pengajuan)
            ->findOrFail($kdlsp_apl01_dokumen);

        Storage::disk('public')->delete($dokumen->file_path);
        $dokumen->delete();

        return response()->json(['message' => 'Dokumen dihapus']);
    }

    private function authorizeAccess(LspApl01Pengajuan $pengajuan, bool $mutating = false): void
    {
        $role = session('user.role');
        if ($role === 'mahasiswa') {
            $user = LspUser::where('username', session('user.username'))->firstOrFail();
            if ($pengajuan->kdlsp_user !== $user->kdlsp_user) {
                abort(403, 'Bukan milik Anda');
            }
            if (!$this->cekPembayaran($pengajuan->kdlsp_apl01_pengajuan)) {
                abort(422, 'Pembayaran belum diterima');
            }

            return;
        }

        if (in_array($role, ['admin', 'superadmin', 'tendik'])) {
            abort_if($mutating, 403, 'Admin hanya dapat memverifikasi dokumen; berkas asli hanya dapat diubah oleh asesi');
            return;
        }

        if (in_array($role, ['dosen', 'asesor_luar']) && !$mutating) {
            $user = LspUser::where('username', session('user.username'))->firstOrFail();
            $isAssigned = $pengajuan->assessmentProcess()
                ->where('assessor_id', $user->kdlsp_user)
                ->exists();
            abort_unless($isAssigned, 403, 'Anda bukan asesor yang ditugaskan untuk pengajuan ini');
            return;
        }

        abort(403, 'Anda tidak berhak mengakses dokumen pengajuan ini');
    }

    public function updateStatus(Request $request, $kdlsp_apl01_pengajuan, $kdlsp_apl01_dokumen)
    {
        $this->authorizeAdminRole();

        $request->validate([
            'status' => 'required|in:menunggu,memenuhi,tidak_memenuhi',
        ]);

        $dokumen = LspApl01Dokumen::where('kdlsp_apl01_pengajuan', $kdlsp_apl01_pengajuan)
            ->findOrFail($kdlsp_apl01_dokumen);

        $dokumen->update(['status' => $request->status]);

        return response()->json($dokumen);
    }

    private function authorizeAdminRole(): void
    {
        $role = session('user.role');
        if (!in_array($role, ['admin', 'superadmin', 'tendik'])) {
            abort(403);
        }
    }



}
