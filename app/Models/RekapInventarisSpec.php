<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RekapInventarisSpec extends Model
{
    protected $table = 'rekap_inventaris_specs';

    protected $fillable = [
        'rekap_inventaris_periode_id',
        'kode_spek',
        'urutan_kode',
        'fingerprint',
    ];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(RekapInventarisPeriode::class, 'rekap_inventaris_periode_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(RekapInventarisSpecDetail::class, 'rekap_inventaris_spec_id')
            ->orderBy('urutan');
    }

    public function pcs(): HasMany
    {
        return $this->hasMany(RekapInventarisPc::class, 'rekap_inventaris_spec_id');
    }
}