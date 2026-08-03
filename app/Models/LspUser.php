<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LspUser extends Model
{
    protected $table      = 'lsp_user';
    protected $primaryKey = 'kdlsp_user';

    protected $guarded = ['kdlsp_user'];

    protected $hidden = ['password'];

    protected $casts = [
        'isAsesor' => 'boolean',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(PtPerson::class, 'kdperson', 'kdperson');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(PtUnitKerja::class, 'kdunit', 'kdunitkerja');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(LspUserSignature::class, 'kdlsp_user', 'kdlsp_user');
    }

    public function activeSignature(): HasOne
    {
        return $this->hasOne(LspUserSignature::class, 'kdlsp_user', 'kdlsp_user')
            ->where('is_active', true)
            ->latest('kdlsp_user_signature');
    }
}
