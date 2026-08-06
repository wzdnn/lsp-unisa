<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LspSkema extends Model
{
    protected $table = 'lsp_skema';
    protected $primaryKey = 'kdlsp_skema';

    protected $fillable = [
        'skema',
        'no_skema',
        'isActive',
    ];

    protected $casts = [
        'isActive' => 'boolean',
    ];

    // Skema ini ada di periode mana saja (via pivot)
    public function periode(): BelongsToMany
    {
        return $this->belongsToMany(
            LspPeriode::class,
            'lsp_periode_skema',
            'kdlsp_skema',
            'kdlsp_periode'
        )->withPivot('kdlsp_periode_masa')->withTimestamps();
    }

    // Detail plotting
    public function periodeSkema(): HasMany
    {
        return $this->hasMany(LspPeriodeSkema::class, 'kdlsp_skema', 'kdlsp_skema');
    }

    public function tarif()
{
    return $this->hasOne(LspSkemaTarif::class, 'kdlsp_skema', 'kdlsp_skema');
}
}
