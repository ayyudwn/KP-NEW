<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RekapInventarisPeriode extends Model
{
    protected $table = 'rekap_inventaris_periodes';

    protected $fillable = [
        'bulan',
        'tahun',
        'nama_periode',
    ];

    public function pcs(): HasMany
    {
        return $this->hasMany(RekapInventarisPc::class, 'rekap_inventaris_periode_id');
    }

    public function specs(): HasMany
    {
        return $this->hasMany(RekapInventarisSpec::class, 'rekap_inventaris_periode_id');
    }
}