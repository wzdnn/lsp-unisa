<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LspSkemaUnitKompetensi extends Model
{
    protected $table = 'lsp_skema_unitkompetensi';
    protected $primaryKey = 'kdlsp_skema_unitkompetensi';

    protected $guarded = ['kdlsp_skema_unitkompetensi'];

    public function skema(): BelongsTo
    {
        return $this->belongsTo(LspSkema::class, 'kdlsp_skema', 'kdlsp_skema');
    }
}
