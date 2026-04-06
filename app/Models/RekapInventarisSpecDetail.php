<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekapInventarisSpecDetail extends Model
{
    protected $table = 'rekap_inventaris_spec_details';

    protected $fillable = [
        'rekap_inventaris_spec_id',
        'komponen',
        'detail',
        'kondisi',
        'catatan_kondisi',
        'urutan',
    ];

    public function spec(): BelongsTo
    {
        return $this->belongsTo(RekapInventarisSpec::class, 'rekap_inventaris_spec_id');
    }
}