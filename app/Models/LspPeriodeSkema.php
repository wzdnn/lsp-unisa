<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LspPeriodeSkema extends Model
{
    protected $table = 'lsp_periode_skema';

    protected $primaryKey = 'kdlsp_periode_skema';

    protected $fillable = [
        'kdlsp_periode',
        'kdlsp_periode_masa',
        'kdlsp_skema',
    ];

    // Relasi ke Periode
    public function periode(): BelongsTo
    {
        return $this->belongsTo(LspPeriode::class, 'kdlsp_periode', 'kdlsp_periode');
    }

    // Relasi ke Masa Periode
    public function masaPeriode(): BelongsTo
    {
        return $this->belongsTo(LspPeriodeMasa::class, 'kdlsp_periode_masa', 'kdlsp_periode_masa');
    }

    // Relasi ke Skema
    public function skema(): BelongsTo
    {
        return $this->belongsTo(LspSkema::class, 'kdlsp_skema', 'kdlsp_skema');
    }
}
