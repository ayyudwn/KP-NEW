<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarangPinjam extends Model
{
    protected $table = 'barang_pinjam';

    protected $fillable = [
        'rekap_inventaris_periode_id',
        'nama_barang',
        'posisi_asal',
        'jumlah',
        'keterangan',
    ];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(RekapInventarisPeriode::class, 'rekap_inventaris_periode_id');
    }
}