<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LspUserSignature extends Model
{
    protected $table = 'lsp_user_signature';

    protected $primaryKey = 'kdlsp_user_signature';

    protected $guarded = ['kdlsp_user_signature'];

    protected $appends = ['file_path', 'file_url'];

    protected $casts = [
        'is_active' => 'boolean',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(LspUser::class, 'kdlsp_user', 'kdlsp_user');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(LspUser::class, 'revoked_by', 'kdlsp_user');
    }

    public function getFilePathAttribute(): ?string
    {
        return $this->attributes['file_path']
            ?? $this->attributes['file_type']
            ?? null;
    }

    public function getFileUrlAttribute(): ?string
    {
        $path = $this->file_path;

        if (!$path) {
            return null;
        }

        return Storage::disk($this->file_disk ?: 'public')->url($path);
    }
}
