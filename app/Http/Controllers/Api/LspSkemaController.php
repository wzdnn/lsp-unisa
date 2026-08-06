<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LspSkema;
use Illuminate\Http\Request;

class LspSkemaController extends Controller
{
    public function index()
    {
        $skema = LspSkema::with('tarif')->orderBy('skema', 'asc')->get();
    return response()->json($skema);
    }

    public function store(Request $request)
    {
        $this->authorizeAdminRole();

        $validated = $request->validate([
            'skema'    => 'required|string|max:200|unique:lsp_skema,skema',
            'no_skema' => 'required|string|max:255',
        ]);

        $skema = LspSkema::create([
            'skema'    => $validated['skema'],
            'no_skema' => $validated['no_skema'],
            'isActive' => true,
        ]);

        return response()->json($skema, 201);
    }

    public function show($kdlsp_skema)
    {
        $skema = LspSkema::with('periodeSkema.periode', 'periodeSkema.masaPeriode', 'tarif')
            ->findOrFail($kdlsp_skema);

        return response()->json($skema);
    }

    public function update(Request $request, $kdlsp_skema)
    {
        $this->authorizeAdminRole();

        $skema = LspSkema::findOrFail($kdlsp_skema);

        $validated = $request->validate([
            'skema'    => 'required|string|max:200|unique:lsp_skema,skema,' . $kdlsp_skema . ',kdlsp_skema',
            'no_skema' => 'required|string|max:255',
            'isActive' => 'sometimes|boolean',
        ]);

        $skema->update($validated);

        return response()->json($skema);
    }

    public function destroy($kdlsp_skema)
    {
        $this->authorizeAdminRole();

        $skema = LspSkema::findOrFail($kdlsp_skema);
        $skema->delete();

        return response()->json(['message' => 'Skema berhasil dihapus']);
    }

    // Toggle aktif/nonaktif
    public function toggle($kdlsp_skema)
    {
        $this->authorizeAdminRole();

        $skema = LspSkema::findOrFail($kdlsp_skema);
        $skema->update(['isActive' => !$skema->isActive]);

        return response()->json([
            'message'  => 'Status skema diperbarui',
            'isActive' => $skema->isActive,
        ]);
    }

    private function authorizeAdminRole()
    {
        $role = session('user.role');
        if (!in_array($role, ['admin', 'superadmin'])) {
            abort(403, 'Hanya admin yang dapat melakukan aksi ini');
        }
    }
}
