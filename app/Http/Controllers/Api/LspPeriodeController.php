<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LspPeriode;
use Illuminate\Http\Request;

class LspPeriodeController extends Controller
{
    public function index()
    {
        $periode = LspPeriode::with(['masaPeriode', 'periodeSkema.skema', 'periodeSkema.masaPeriode'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($periode);
    }

    public function store(Request $request)
    {
        $request->validate([
            'periode' => 'required|string|max:100|unique:lsp_periode,periode',
        ]);

        $this->authorizeAdminRole();

        $periode = LspPeriode::create([
            'periode' => $request->periode,
        ]);

        return response()->json($periode->load('masaPeriode'), 201);
    }

    public function show($kdlsp_periode)
    {
        $periode = LspPeriode::with([
            'masaPeriode',
            'periodeSkema.skema',
            'periodeSkema.masaPeriode'
        ])->findOrFail($kdlsp_periode);

        return response()->json($periode);
    }

    public function update(Request $request, $kdlsp_periode)
    {
        $this->authorizeAdminRole();

        $periode = LspPeriode::findOrFail($kdlsp_periode);

        $request->validate([
            'periode' => 'required|string|max:100|unique:lsp_periode,periode,' . $kdlsp_periode . ',kdlsp_periode',
        ]);

        $periode->update(['periode' => $request->periode]);

        return response()->json($periode->load('masaPeriode'));
    }

    public function destroy($kdlsp_periode)
    {
        $this->authorizeAdminRole();

        $periode = LspPeriode::findOrFail($kdlsp_periode);
        $periode->delete();

        return response()->json(['message' => 'Periode berhasil dihapus']);
    }

    private function authorizeAdminRole()
    {
        $role = session('user.role');
        if (!in_array($role, ['admin', 'superadmin'])) {
            abort(403, 'Hanya admin yang dapat melakukan aksi ini');
        }
    }
}
