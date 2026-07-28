<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LspPeriode;
use App\Models\LspPeriodeMasa;
use Illuminate\Http\Request;

class LspPeriodeMasaController extends Controller
{
    public function index($kdlsp_periode)
    {
        $periode = LspPeriode::findOrFail($kdlsp_periode);

        $masa = $periode->masaPeriode()
            ->orderBy('tanggal_mulai', 'asc')
            ->get();

        return response()->json($masa);
    }

    public function store(Request $request, $kdlsp_periode)
    {
        $this->authorizeAdminRole();

        LspPeriode::findOrFail($kdlsp_periode);

        $request->validate([
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
        ]);

        // Cek overlap masa dalam periode yang sama
        $overlap = LspPeriodeMasa::where('kdlsp_periode', $kdlsp_periode)
            ->where(function ($q) use ($request) {
                $q->whereBetween('tanggal_mulai', [$request->tanggal_mulai, $request->tanggal_selesai])
                  ->orWhereBetween('tanggal_selesai', [$request->tanggal_mulai, $request->tanggal_selesai])
                  ->orWhere(function ($q2) use ($request) {
                      $q2->where('tanggal_mulai', '<=', $request->tanggal_mulai)
                         ->where('tanggal_selesai', '>=', $request->tanggal_selesai);
                  });
            })->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'Rentang tanggal bertabrakan dengan masa periode yang sudah ada'
            ], 422);
        }

        $masa = LspPeriodeMasa::create([
            'kdlsp_periode'   => $kdlsp_periode,
            'tanggal_mulai'   => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
        ]);

        return response()->json($masa->load('periode'), 201);
    }

    public function show($kdlsp_periode, $kdlsp_periode_masa)
    {
        $masa = LspPeriodeMasa::where('kdlsp_periode', $kdlsp_periode)
            ->with('periode')
            ->findOrFail($kdlsp_periode_masa);

        return response()->json($masa);
    }

    public function update(Request $request, $kdlsp_periode, $kdlsp_periode_masa)
    {
        $this->authorizeAdminRole();

        $masa = LspPeriodeMasa::where('kdlsp_periode', $kdlsp_periode)
            ->findOrFail($kdlsp_periode_masa);

        $request->validate([
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
        ]);

        // Cek overlap, kecuali data diri sendiri
        $overlap = LspPeriodeMasa::where('kdlsp_periode', $kdlsp_periode)
            ->where('kdlsp_periode_masa', '!=', $kdlsp_periode_masa)
            ->where(function ($q) use ($request) {
                $q->whereBetween('tanggal_mulai', [$request->tanggal_mulai, $request->tanggal_selesai])
                  ->orWhereBetween('tanggal_selesai', [$request->tanggal_mulai, $request->tanggal_selesai])
                  ->orWhere(function ($q2) use ($request) {
                      $q2->where('tanggal_mulai', '<=', $request->tanggal_mulai)
                         ->where('tanggal_selesai', '>=', $request->tanggal_selesai);
                  });
            })->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'Rentang tanggal bertabrakan dengan masa periode yang sudah ada'
            ], 422);
        }

        $masa->update([
            'tanggal_mulai'   => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
        ]);

        return response()->json($masa->load('periode'));
    }

    public function destroy($kdlsp_periode, $kdlsp_periode_masa)
    {
        $this->authorizeAdminRole();

        $masa = LspPeriodeMasa::where('kdlsp_periode', $kdlsp_periode)
            ->findOrFail($kdlsp_periode_masa);

        $masa->delete();

        return response()->json(['message' => 'Masa periode berhasil dihapus']);
    }

    private function authorizeAdminRole()
    {
        $role = session('user.role');
        if (!in_array($role, ['admin', 'superadmin'])) {
            abort(403, 'Hanya admin yang dapat melakukan aksi ini');
        }
    }

    public function toggle($kdlsp_periode, $kdlsp_periode_masa)
    {
        $this->authorizeAdminRole();

        $masa = LspPeriodeMasa::where('kdlsp_periode', $kdlsp_periode)
            ->findOrFail($kdlsp_periode_masa);

        $masa->update(['isActive' => !$masa->isActive]);

        return response()->json([
            'message'  => 'Status masa periode diperbarui',
            'isActive' => $masa->isActive,
        ]);
    }
}
