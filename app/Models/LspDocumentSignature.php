<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LspDocumentSignature extends Model
{
    protected $table = 'lsp_document_signature';

    protected $primaryKey = 'kdlsp_document_signature';

    protected $guarded = ['kdlsp_document_signature'];

    protected $casts = [
        'signed_at' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(LspUser::class, 'kdlsp_user', 'kdlsp_user');
    }

    public function userSignature(): BelongsTo
    {
        return $this->belongsTo(
            LspUserSignature::class,
            'kdlsp_user_signature',
            'kdlsp_user_signature',
        );
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(LspUser::class, 'revoked_by', 'kdlsp_user');
    }
}
