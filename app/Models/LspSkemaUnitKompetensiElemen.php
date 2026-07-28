<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LspSkemaUnitKompetensiElemen extends Model
{
    protected $table = 'lsp_skema_unitkompetensi_elemen';
    protected $primaryKey = 'kdlsp_skema_unitkompetensi_elemen';

    protected $guarded = ['kdlsp_skema_unitkompetensi_elemen'];
}
