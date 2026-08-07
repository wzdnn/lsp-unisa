<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LspUser;
use App\Models\PtPerson;
use App\Models\PtUnitKerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LspUserController extends Controller
{
    // List semua user, bisa filter by role
    public function index(Request $request)
    {
        $this->authorizeAdminRole();

        $query = LspUser::with(['person', 'unitKerja'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('isAsesor')) {
            $query->where('isAsesor', $request->boolean('isAsesor'));
        }

        if ($request->filled('search')) {
            $query->where('username', 'like', '%' . $request->search . '%');
        }

        return response()->json($query->get());
    }

    // List dosen internal (untuk pilih asesor)
    public function dosenInternal()
    {
        $this->authorizeAdminRole();

        $dosen = LspUser::with(['person', 'unitKerja'])
            ->where('role', 'dosen')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($dosen);
    }

    // List semua asesor (dosen internal yg isAsesor=true + asesor_luar)
    public function asesor()
    {
        return response()->json(
            LspUser::where('isAsesor', true)->get()
        );
    }

    // Toggle isAsesor untuk dosen internal
    public function toggleAsesor($kdlsp_user)
{
    $this->authorizeAdminRole();

    $user = LspUser::findOrFail($kdlsp_user);

    // Izinkan dosen internal dan asesor luar
    if (!in_array($user->role, ['dosen', 'asesor_luar'])) {
        return response()->json([
            'message' => 'Hanya dosen atau asesor luar yang dapat diubah status asesornya'
        ], 422);
    }

    $user->update(['isAsesor' => !$user->isAsesor]);

    return response()->json([
        'message'  => 'Status asesor diperbarui',
        'isAsesor' => $user->isAsesor,
    ]);
}

    // Tambah asesor luar (manual)
    public function storeAsesorLuar(Request $request)
    {
        $this->authorizeAdminRole();

        $request->validate([
            'username' => 'required|string|max:100|unique:lsp_user,username',
            'password' => 'required|string|min:6',
            'namalengkap' => 'required|string|max:200',
        ]);

        $user = LspUser::create([
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'namalengkap' => $request->namalengkap,
            'role' => 'asesor_luar',
            'isAsesor' => 1,
        ]);

        return response()->json($user, 201);
    }

    // Update asesor luar
    public function update(Request $request, $kdlsp_user)
    {
        $this->authorizeAdminRole();

        $user = LspUser::findOrFail($kdlsp_user);

        if ($user->role !== 'asesor_luar') {
            return response()->json(['message' => 'Hanya asesor luar yang bisa diedit'], 422);
        }

        $request->validate([
            'username' => 'sometimes|string|max:100|unique:lsp_user,username,' . $kdlsp_user . ',kdlsp_user',
            'password' => 'nullable|string|min:6',
            'namalengkap' => 'sometimes|string|max:200',
        ]);

        $updateData = array_filter([
            'username' => $request->username,
            'namalengkap' => $request->namalengkap,
        ]);

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return response()->json($user);
    }

    // Hapus asesor luar
    public function destroy($kdlsp_user)
    {
        $this->authorizeAdminRole();

        $user = LspUser::findOrFail($kdlsp_user);

        if ($user->role !== 'asesor_luar') {
            return response()->json(['message' => 'Hanya asesor luar yang bisa dihapus'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Asesor luar berhasil dihapus']);
    }

    // List unit kerja (untuk dropdown)
    public function unitKerja()
    {
        $units = PtUnitKerja::select('kdunitkerja', 'unitkerja', 'unitkerjapendek')
            ->orderBy('unitkerja')
            ->get();

        return response()->json($units);
    }

    private function authorizeAdminRole(): void
    {
        $role = session('user.role');
        if (!in_array($role, ['admin', 'superadmin', 'tendik'])) {
            abort(403, 'Hanya admin yang dapat melakukan aksi ini');
        }
    }
}
