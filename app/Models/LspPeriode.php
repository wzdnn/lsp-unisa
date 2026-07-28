<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LspPeriode extends Model
{
    protected $table = 'lsp_periode';
    protected $primaryKey = 'kdlsp_periode';

    protected $fillable = [
        'periode',
    ];

    // Satu periode punya banyak masa
    public function masaPeriode(): HasMany
    {
        return $this->hasMany(LspPeriodeMasa::class, 'kdlsp_periode', 'kdlsp_periode');
    }

    // Satu periode punya banyak skema (via pivot)
    public function skema(): BelongsToMany
    {
        return $this->belongsToMany(
            LspSkema::class,
            'lsp_periode_skema',
            'kdlsp_periode',
            'kdlsp_skema'
        )->withPivot('kdlsp_periode_masa')->withTimestamps();
    }

    // Plotting skema (via tabel pivot lengkap)
    public function periodeSkema(): HasMany
    {
        return $this->hasMany(LspPeriodeSkema::class, 'kdlsp_periode', 'kdlsp_periode');
    }
}
