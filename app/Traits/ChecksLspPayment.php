<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait ChecksLspPayment
{
    protected function cekPembayaran(int $kdlsp_apl01_pengajuan): bool
    {
        return DB::connection('spc')->table('tagihan')
            ->where('referensisimptt', $kdlsp_apl01_pengajuan)
            ->whereNotNull('tglbayar')
            ->exists();
    }

    protected function batasPembayaranTagihan(int $kdlsp_apl01_pengajuan): ?string
    {
        return DB::connection('spc')->table('tagihan')
            ->where('referensisimptt', $kdlsp_apl01_pengajuan)
            ->value('waktututuptagihan');
    }
}
