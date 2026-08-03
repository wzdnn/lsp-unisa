<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LspApl01DokumenController;
use App\Http\Controllers\Api\LspApl01PengajuanController;
use App\Http\Controllers\Api\LspPeriodeController;
use App\Http\Controllers\Api\LspPeriodeMasaController;
use App\Http\Controllers\Api\LspPeriodeSkemaController;
use App\Http\Controllers\Api\LspSkemaController;
use App\Http\Controllers\Api\LspSkemaUnitKompetensiController;
use App\Http\Controllers\Api\LspSignatureController;
use App\Http\Controllers\Api\LspUserController;
use Illuminate\Support\Facades\Route;

// Hapus login/me/logout dari sini — sudah dipindah ke web.php

// Protected routes — tambah 'web' agar session terbaca
Route::middleware(['web', 'auth.session'])->group(function () {

    Route::get('/dashboard', function () {
        return response()->json([
            'message' => 'Welcome dashboard',
            'user'    => session('user'),
        ]);
    });

    Route::get('/admin-only', function () {
        return response()->json(['message' => 'Admin area']);
    })->middleware('role:admin,superadmin,tendik');

    Route::get('/dosen-only', function () {
        return response()->json(['message' => 'Dosen area']);
    })->middleware('role:dosen');

    Route::get('/mahasiswa-only', function () {
        return response()->json(['message' => 'Mahasiswa area']);
    })->middleware('role:mahasiswa');

    Route::apiResource('periode', LspPeriodeController::class)
         ->parameters(['periode' => 'kdlsp_periode']);

    Route::apiResource('periode.masa', LspPeriodeMasaController::class)
         ->parameters(['masa' => 'kdlsp_periode_masa']);
    Route::patch('periode/{kdlsp_periode}/masa/{kdlsp_periode_masa}/toggle',
        [LspPeriodeMasaController::class, 'toggle']);

    Route::apiResource('skema', LspSkemaController::class)
         ->parameters(['skema' => 'kdlsp_skema']);
    Route::patch('skema/{kdlsp_skema}/toggle', [LspSkemaController::class, 'toggle']);

    Route::delete('periode-skema/bulk', [LspPeriodeSkemaController::class, 'bulkDestroy']);
    Route::apiResource('periode-skema', LspPeriodeSkemaController::class);

    Route::delete('unit-kompetensi/bulk', [LspSkemaUnitKompetensiController::class, 'bulkDestroy']);
    Route::apiResource('unit-kompetensi', LspSkemaUnitKompetensiController::class)
         ->parameters(['unit-kompetensi' => 'kdlsp_skema_unitkompetensi']);

    Route::get('apl01-pengajuan/current', [LspApl01PengajuanController::class, 'current']);
    Route::patch('apl01-pengajuan/{kdlsp_apl01_pengajuan}/review', [LspApl01PengajuanController::class, 'review']);
    Route::apiResource('apl01-pengajuan', LspApl01PengajuanController::class)
         ->only(['index', 'store', 'show'])
         ->parameters(['apl01-pengajuan' => 'kdlsp_apl01_pengajuan']);

    Route::get('signature/current', [LspSignatureController::class, 'current']);
    Route::post('signature', [LspSignatureController::class, 'store']);

    Route::get('/apl01-pengajuan/{id}/dokumen', [LspApl01DokumenController::class, 'index']);
    Route::post('/apl01-pengajuan/{id}/dokumen', [LspApl01DokumenController::class, 'store']);
    Route::delete('/apl01-pengajuan/{id}/dokumen/{kdlsp_apl01_dokumen}',
    [LspApl01DokumenController::class, 'destroy']);
    Route::patch('/apl01-pengajuan/{id}/dokumen/{kdlsp_apl01_dokumen}/status',
    [LspApl01DokumenController::class, 'updateStatus']);

    Route::get('users',                [LspUserController::class, 'index']);
    Route::get('users/dosen-internal', [LspUserController::class, 'dosenInternal']);
    Route::get('users/asesor',         [LspUserController::class, 'asesor']);
    Route::get('users/unit-kerja',     [LspUserController::class, 'unitKerja']);
    Route::patch('users/{kdlsp_user}/toggle-asesor', [LspUserController::class, 'toggleAsesor']);
    Route::post('users/asesor-luar',   [LspUserController::class, 'storeAsesorLuar']);
    Route::put('users/{kdlsp_user}',   [LspUserController::class, 'update']);
    Route::delete('users/{kdlsp_user}',[LspUserController::class, 'destroy']);
});
