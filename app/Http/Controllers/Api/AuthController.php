<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use App\Models\LspUser;
use App\Models\PtPerson;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => 'required',
        ]);

        $username = $request->username;
        $password = $request->password;

        $admins = [
            'admin1' => '123456',
            'admin2' => '123456',
        ];

        $superAdmins = [
            'super1' => '123456',
        ];

        // Superadmin — tidak dicatat di lsp_user
        if (isset($superAdmins[$username]) && $superAdmins[$username] === $password) {
            session(['user' => ['username' => $username, 'role' => 'superadmin']]);
            return response()->json(['message' => 'Login superadmin berhasil']);
        }

        // Admin — tidak dicatat di lsp_user
        if (isset($admins[$username]) && $admins[$username] === $password) {
            session(['user' => ['username' => $username, 'role' => 'admin']]);
            return response()->json(['message' => 'Login admin berhasil']);
        }

        // SSO eksternal
        $response = Http::withoutVerifying()
            ->asForm()
            ->post('https://service.unisayogya.ac.id/loginall.php', [
                'n' => $username,
                'p' => $password,
            ]);

        if (!$response->successful()) {
            return response()->json(['message' => 'Gagal koneksi'], 500);
        }

        $data = $response->json();

        if (!isset($data['isallowed']) || $data['isallowed'] !== true) {
            return response()->json(['message' => 'Login gagal'], 401);
        }

        $role = $data['loginas'] ?? null;

        if (!in_array($role, ['dosen', 'tendik', 'mahasiswa'])) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        // Auto-record user ke lsp_user
        $this->recordUser($username, $role);

        session(['user' => ['username' => $username, 'role' => $role]]);
        return response()->json(['message' => 'Login berhasil']);
    }

    private function recordUser(string $username, string $role): void
    {
        $kdperson   = null;
        $kdunitkerja = null;

        if ($role === 'mahasiswa') {
            // Mahasiswa: cari via ak_mahasiswa by NIM
            $query = DB::table('ak_mahasiswa as mhs')
                ->leftJoin('pt_person as pp', 'pp.kdperson', '=', 'mhs.kdperson')
                ->select(
                    'pp.kdperson',
                    'mhs.kdunitkerja'
                )
                ->where('mhs.nim', $username)
                ->first();

            $kdperson    = $query?->kdperson;
            $kdunitkerja = $query?->kdunitkerja;

        } elseif (in_array($role, ['dosen', 'tendik'])) {
            // Dosen & Tendik: cari via pt_user by kodeuser
            $query = DB::table('pt_user as pu')
                ->leftJoin('pt_person as pp', 'pp.kdperson', '=', 'pu.kdperson')
                ->leftJoin('ak_dosen as dos', 'dos.kdperson', '=', 'pp.kdperson')
                ->select(
                    'pp.kdperson',
                    'dos.kdunitkerja'
                )
                ->where('pu.kodeuser', $username)
                ->first();

            $kdperson    = $query?->kdperson;
            $kdunitkerja = $query?->kdunitkerja;
        }

        $lspRole = match($role) {
            'mahasiswa' => 'mahasiswa',
            'dosen'     => 'dosen',
            'tendik'    => 'tendik',
            default     => 'mahasiswa',
        };

        LspUser::updateOrCreate(
            ['username' => $username],
            [
                'role'     => $lspRole,
                'kdperson' => $kdperson,
                'kdunit'   => $kdunitkerja,
            ]
        );
    }

    public function me()
    {

        //  \Log::info('Session ID: ' . session()->getId());
        // \Log::info('Session has user: ' . (session()->has('user') ? 'yes' : 'no'));
        // \Log::info('All session data: ', session()->all());
        // \Log::info('Cookie header: ' . request()->header('Cookie', 'NONE'));

        if (!session()->has('user')) {
        return response()->json(['user' => null], 200);
        }

        $sessionUser = session('user');

        $lspUser = LspUser::with(['person', 'unitKerja'])
            ->where('username', $sessionUser['username'])
            ->first();

        return response()->json([
            'username' => $sessionUser['username'],
            'role'     => $sessionUser['role'],
            'lsp_user' => $lspUser,
        ]);
    }

    public function logout()
    {
        session()->forget('user');
        return response()->json(['message' => 'Logout berhasil']);
    }
}
