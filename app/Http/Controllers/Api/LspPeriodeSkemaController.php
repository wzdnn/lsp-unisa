<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LspPeriodeSkema;
use App\Models\LspPeriodeMasa;
use Illuminate\Http\Request;

class LspPeriodeSkemaController extends Controller
{
    // Ambil semua plotting, bisa filter by periode
    public function index(Request $request)
    {
        $query = LspPeriodeSkema::with(['periode', 'masaPeriode', 'skema']);

        if ($request->filled('kdlsp_periode')) {
            $query->where('kdlsp_periode', $request->kdlsp_periode);
        }

        if ($request->filled('kdlsp_periode_masa')) {
            $query->where('kdlsp_periode_masa', $request->kdlsp_periode_masa);
        }

        return response()->json($query->get());
    }

    // Plot satu skema ke periode + masa tertentu
    public function store(Request $request)
    {
        $this->authorizeAdminRole();

        $request->validate([
            'kdlsp_periode'      => 'required|exists:lsp_periode,kdlsp_periode',
            'kdlsp_periode_masa' => 'required|exists:lsp_periode_masa,kdlsp_periode_masa',
            'kdlsp_skema'        => 'required|exists:lsp_skema,kdlsp_skema',
        ]);

        // Pastikan masa periode milik periode yang dipilih
        $masaValid = LspPeriodeMasa::where('kdlsp_periode_masa', $request->kdlsp_periode_masa)
            ->where('kdlsp_periode', $request->kdlsp_periode)
            ->exists();

        if (!$masaValid) {
            return response()->json([
                'message' => 'Masa periode tidak sesuai dengan periode yang dipilih'
            ], 422);
        }

        // Cek duplikasi
        $exists = LspPeriodeSkema::where('kdlsp_periode', $request->kdlsp_periode)
            ->where('kdlsp_periode_masa', $request->kdlsp_periode_masa)
            ->where('kdlsp_skema', $request->kdlsp_skema)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Skema sudah diplot pada periode dan masa ini'
            ], 422);
        }

        $plot = LspPeriodeSkema::create($request->only(
            'kdlsp_periode',
            'kdlsp_periode_masa',
            'kdlsp_skema'
        ));

        return response()->json($plot->load(['periode', 'masaPeriode', 'skema']), 201);
    }

    public function show($kdlsp_periode_skema)
    {
        $plot = LspPeriodeSkema::with(['periode', 'masaPeriode', 'skema'])->findOrFail($kdlsp_periode_skema);
        return response()->json($plot);
    }

    public function update(Request $request, $kdlsp_periode_skema)
    {
        return response()->json(['message' => 'Gunakan delete dan buat plotting baru'], 405);
    }

    public function destroy($kdlsp_periode_skema)
    {
        $this->authorizeAdminRole();

        $plot = LspPeriodeSkema::findOrFail($kdlsp_periode_skema);
        $plot->delete();

        return response()->json(['message' => 'Plot skema berhasil dihapus']);
    }

    // Hapus banyak plotting sekaligus
    public function bulkDestroy(Request $request)
    {
        $this->authorizeAdminRole();

        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:lsp_periode_skema,kdlsp_periode_skema',
        ]);

        LspPeriodeSkema::whereIn('kdlsp_periode_skema', $request->ids)->delete();

        return response()->json(['message' => 'Plot skema berhasil dihapus']);
    }

    private function authorizeAdminRole()
    {
        $role = session('user.role');
        if (!in_array($role, ['admin', 'superadmin'])) {
            abort(403, 'Hanya admin yang dapat melakukan aksi ini');
        }
    }
}
