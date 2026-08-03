<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LspUser;
use App\Models\LspUserSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LspSignatureController extends Controller
{
    public function current()
    {
        return response()->json($this->activeSignatureFor($this->currentLspUser()));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'signature' => 'required|string',
            'consent_text' => 'nullable|string',
        ]);

        $image = $this->decodeSignatureImage($validated['signature']);
        $user = $this->currentLspUser();
        $path = sprintf(
            'user-signatures/%s/signature-%s-%s.%s',
            $user->kdlsp_user,
            now()->format('YmdHis'),
            Str::random(8),
            $image['extension'],
        );

        Storage::disk('public')->put($path, $image['binary']);

        $pathColumn = $this->signaturePathColumn();

        try {
            $signature = DB::transaction(function () use ($request, $validated, $user, $path, $pathColumn, $image) {
                LspUserSignature::where('kdlsp_user', $user->kdlsp_user)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'revoked_at' => now(),
                        'revoked_by' => $user->kdlsp_user,
                        'revoked_reason' => 'Diganti dengan tanda tangan baru',
                    ]);

                return LspUserSignature::create([
                    'kdlsp_user' => $user->kdlsp_user,
                    'signature_type' => 'drawn',
                    $pathColumn => $path,
                    'file_disk' => 'public',
                    'mime_type' => $image['mime_type'],
                    'file_size' => strlen($image['binary']),
                    'file_hash' => hash('sha256', $image['binary']),
                    'is_active' => true,
                    'consent_text' => $validated['consent_text'] ?? null,
                    'created_ip' => $request->ip(),
                    'created_user_agent' => (string) $request->userAgent(),
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        return response()->json($signature->fresh(), 201);
    }

    private function activeSignatureFor(LspUser $user): ?LspUserSignature
    {
        return LspUserSignature::where('kdlsp_user', $user->kdlsp_user)
            ->where('is_active', true)
            ->latest('kdlsp_user_signature')
            ->first();
    }

    private function currentLspUser(): LspUser
    {
        $username = session('user.username');

        $user = $username
            ? LspUser::where('username', $username)->first()
            : null;

        if (!$user) {
            abort(422, 'Data user LSP tidak ditemukan');
        }

        return $user;
    }

    private function decodeSignatureImage(string $signature): array
    {
        if (!preg_match('/^data:image\/(png|jpeg);base64,/', $signature, $matches)) {
            abort(422, 'Format tanda tangan tidak valid');
        }

        [, $encoded] = explode(',', $signature, 2);
        $binary = base64_decode($encoded, true);

        if ($binary === false || strlen($binary) === 0) {
            abort(422, 'Tanda tangan tidak dapat dibaca');
        }

        if (strlen($binary) > 2 * 1024 * 1024) {
            abort(422, 'Ukuran tanda tangan terlalu besar');
        }

        $type = $matches[1];

        return [
            'binary' => $binary,
            'extension' => $type === 'jpeg' ? 'jpg' : 'png',
            'mime_type' => $type === 'jpeg' ? 'image/jpeg' : 'image/png',
        ];
    }

    private function signaturePathColumn(): string
    {
        return Schema::hasColumn('lsp_user_signature', 'file_path')
            ? 'file_path'
            : 'file_type';
    }
}
