<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekapInventarisPc extends Model
{
    protected $table = 'rekap_inventaris_pcs';

    protected $fillable = [
        'rekap_inventaris_periode_id',
        'rekap_inventaris_spec_id',
        'no_pc',
        'lokasi',
        'kondisi',
    ];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(RekapInventarisPeriode::class, 'rekap_inventaris_periode_id');
    }

    public function spec(): BelongsTo
    {
        return $this->belongsTo(RekapInventarisSpec::class, 'rekap_inventaris_spec_id');
    }
}