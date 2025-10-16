<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SoftwareDetail extends Model
{
    use HasFactory;

    protected $table = 'software_details';
    protected $fillable = [
        'nama',
        'versi',
        'keterangan',
        'jenis_lisensi',
        'nomor_lisensi',
        'tanggal_kadaluarsa'
    ];

    protected $casts = [
        'tanggal_kadaluarsa' => 'date'
    ];

    public function inventory(): MorphOne
    {
        return $this->morphOne(Inventory::class, 'inventoriable');
    }

    /**
     * Relasi many-to-many ke Course via tabel pivot course_software
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_software');
    }
}
