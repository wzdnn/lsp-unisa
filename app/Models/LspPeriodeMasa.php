<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LspPeriodeMasa extends Model
{
    protected $table = 'lsp_periode_masa';
    protected $primaryKey = 'kdlsp_periode_masa';

    protected $fillable = [
        'kdlsp_periode',
        'isActive',
        'tanggal_mulai',
        'tanggal_selesai',
    ];

    protected $casts = [
        'tanggal_mulai'   => 'date',
        'tanggal_selesai' => 'date',
        'isActive'        => 'boolean',
    ];

    // Masa ini milik periode tertentu
    public function periode(): BelongsTo
    {
        return $this->belongsTo(LspPeriode::class, 'kdlsp_periode', 'kdlsp_periode');
    }

    // Masa ini dipakai di plotting skema mana saja
    public function periodeSkema(): HasMany
    {
        return $this->hasMany(LspPeriodeSkema::class, 'kdlsp_periode_masa', 'kdlsp_periode_masa');
    }
}
