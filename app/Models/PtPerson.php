<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PtPerson extends Model
{
    protected $table      = 'pt_person';
    protected $primaryKey = 'kdperson';
    public $timestamps    = false;

    protected $fillable = [
        'kdperson',
        'namalengkap',
        'gelardepan',
        'gelarbelakang',
        'email',
        'notelpon',
        'foto',
        'guiddosen',
        'guidmahasiswa',
    ];

    // Nama lengkap dengan gelar
    public function getNamaLengkapDenganGelarAttribute(): string
    {
        $depan    = $this->gelardepan    ? $this->gelardepan . ' '    : '';
        $belakang = $this->gelarbelakang ? ', ' . $this->gelarbelakang : '';
        return $depan . $this->namalengkap . $belakang;
    }

    public function lspUser()
    {
        return $this->hasOne(LspUser::class, 'kdperson', 'kdperson');
    }

    public function unitKerja()
    {
        return $this->hasOne(PtUnitKerja::class, 'kdperson', 'kdperson');
    }
}
