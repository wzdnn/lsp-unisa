<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PtUnitKerja extends Model
{
    protected $table      = 'pt_unitkerja';
    protected $primaryKey = 'kdunitkerja';
    public $timestamps    = false;

    protected $fillable = [
        'kdunitkerja',
        'unitkerja',
        'unitkerjapendek',
    ];
}
