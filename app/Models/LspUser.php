<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
