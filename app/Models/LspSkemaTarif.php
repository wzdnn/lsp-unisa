<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LspSkemaTarif extends Model
{
    //
    protected $table = 'lsp_skema_tarif';
    protected $primaryKey = 'kdlsp_skema_tarif';

    protected $guarded = ['kdlsp_skema_tarif'];

    public function skema()
    {
        return $this->belongsTo(LspSkema::class, 'kdlsp_skema', 'kdlsp_skema');
    }
}
