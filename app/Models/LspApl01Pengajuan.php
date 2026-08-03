<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LspApl01Pengajuan extends Model
{
    //

    protected $table = 'lsp_apl01_pengajuan';

    protected $primaryKey = 'kdlsp_apl01_pengajuan';

    protected $guarded = ['kdlsp_apl01_pengajuan'];

    protected $casts = [
        'data_pribadi' => 'array',
        'data_pekerjaan' => 'array',
        'data_sertifikasi' => 'array',
        'data_persyaratan' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(LspUser::class, 'kdlsp_user', 'kdlsp_user');
    }

    public function periodeSkema()
    {
        return $this->belongsTo(LspPeriodeSkema::class, 'kdlsp_periode_skema', 'kdlsp_periode_skema');
    }

    public function reviewer()
    {
        return $this->belongsTo(LspUser::class, 'reviewed_by', 'kdlsp_user');
    }

    public function dokumen()
    {
        return $this->hasMany(LspApl01Dokumen::class, 'kdlsp_apl01_pengajuan', 'kdlsp_apl01_pengajuan');
    }

    public function documentSignatures()
    {
        return $this->hasMany(LspDocumentSignature::class, 'document_id', 'kdlsp_apl01_pengajuan')
            ->where('document_type', 'apl01_pengajuan');
    }

}
