<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LspApl01Dokumen extends Model
{
    protected $table = 'lsp_apl01_dokumen';

    protected $primaryKey = 'kdlsp_apl01_dokumen';

    protected $guarded = ['kdlsp_apl01_dokumen'];

    public function pengajuan()
    {
        return $this->belongsTo(LspApl01Pengajuan::class, 'kdlsp_apl01_pengajuan', 'kdlsp_apl01_pengajuan');
    }
}
