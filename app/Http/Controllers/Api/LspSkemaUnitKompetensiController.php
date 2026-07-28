<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LspSkemaUnitKompetensi;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LspSkemaUnitKompetensiController extends Controller
{
    /**
     * GET /uji-kompetensi?kdlsp_skema=xxx
     * Ambil semua unit kompetensi, bisa difilter per skema
     */
    public function index(Request $request): JsonResponse
    {
        $query = LspSkemaUnitKompetensi::with('skema')
            ->orderBy('kode_unit');

        if ($request->filled('kdlsp_skema')) {
            $query->where('kdlsp_skema', $request->kdlsp_skema);
        }

        return response()->json($query->get());
    }

    /**
     * POST /uji-kompetensi
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kdlsp_skema' => 'required|exists:lsp_skema,kdlsp_skema',
            'kode_unit'   => 'required|string|max:100',
            'judul_unit'  => 'required|string|max:255',
            'standar_kompetensi_kerja' => 'required|string|max:255',
        ]);

        // Cek duplikasi kode_unit dalam skema yang sama
        $exists = LspSkemaUnitKompetensi::where('kdlsp_skema', $validated['kdlsp_skema'])
            ->where('kode_unit', $validated['kode_unit'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Kode unit kompetensi sudah ada pada skema ini',
            ], 422);
        }

        $data = LspSkemaUnitKompetensi::create($validated);

        return response()->json($data->load('skema'), 201);
    }

    /**
     * GET /uji-kompetensi/{id}
     */
    public function show($id): JsonResponse
    {
        $data = LspSkemaUnitKompetensi::with('skema')->findOrFail($id);

        return response()->json($data);
    }

    /**
     * PUT /uji-kompetensi/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        $data = LspSkemaUnitKompetensi::findOrFail($id);

        $validated = $request->validate([
            'kdlsp_skema' => 'required|exists:lsp_skema,kdlsp_skema',
            'kode_unit'   => 'required|string|max:100',
            'judul_unit'  => 'required|string|max:255',
            'standar_kompetensi_kerja' => 'required|string|max:255',
        ]);

        // Cek duplikasi kode_unit dalam skema yang sama, kecuali dirinya sendiri
        $exists = LspSkemaUnitKompetensi::where('kdlsp_skema', $validated['kdlsp_skema'])
            ->where('kode_unit', $validated['kode_unit'])
            ->where('kdlsp_skema_unitkompetensi', '!=', $id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Kode unit kompetensi sudah ada pada skema ini',
            ], 422);
        }

        $data->update($validated);

        return response()->json($data->load('skema'));
    }

    /**
     * DELETE /uji-kompetensi/{id}
     */
    public function destroy($id): JsonResponse
    {
        $data = LspSkemaUnitKompetensi::findOrFail($id);
        $data->delete();

        return response()->json(['message' => 'Unit kompetensi berhasil dihapus']);
    }

    /**
     * DELETE /uji-kompetensi/bulk
     * Hapus banyak sekaligus
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:lsp_ujikompetensi_skema,kdlsp_ujikompetensi_skema',
        ]);

        LspSkemaUnitKompetensi::whereIn('kdlsp_skema_unitkompetensi', $request->ids)->delete();

        return response()->json(['message' => 'Unit kompetensi berhasil dihapus']);
    }
}
