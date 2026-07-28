<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

// Auth routes pakai middleware web agar session & cookie konsisten
Route::middleware('web')->group(function () {
    Route::post('/api/login',  [AuthController::class, 'login']);
    Route::get('/api/me',      [AuthController::class, 'me']);
    Route::post('/api/logout', [AuthController::class, 'logout']);
});

// Catch-all untuk Vue SPA — harus paling bawah
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
