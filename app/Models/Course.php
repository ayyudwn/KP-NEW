<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'sks',
        'semester',
        'jumlah_mahasiswa',
        'prodi_id',
        'software_requirements'
    ];

    protected $casts = [
        'software_requirements' => 'array'
    ];

    protected $appends = ['code_number'];

    /**
     * Get the code_number (numeric part of course code)
     * Contoh: "A11.64504" -> "64504"
     */
    protected function codeNumber(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->code) {
                    return null;
                }
                // Parse angka setelah titik, misal "A11.64504" -> "64504"
                if (str_contains($this->code, '.')) {
                    $parts = explode('.', $this->code);
                    return end($parts);
                }
                // Jika tidak ada titik, return seluruh code
                return $this->code;
            }
        );
    }

    /**
     * Relasi ke model Prodi
     */
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    /**
     * Relasi many-to-many ke SoftwareDetail via tabel pivot course_software
     */
    public function software(): BelongsToMany
    {
        return $this->belongsToMany(SoftwareDetail::class, 'course_software');
    }

    /**
     * Alias untuk software() - untuk konsistensi penamaan
     */
    public function requiredSoftware(): BelongsToMany
    {
        return $this->software();
    }

    /**
     * Relasi ke Schedule
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Menghitung slot akhir berdasarkan SKS
     * 1 SKS = 1 slot (50 menit)
     *
     * @param int $startSlotNumber Nomor slot awal
     * @return int Nomor slot akhir
     */
    public function calculateEndSlot(int $startSlotNumber): int
    {
        return $startSlotNumber + $this->sks - 1;
    }

    /**
     * Mendapatkan durasi dalam menit
     *
     * @return int Durasi dalam menit
     */
    public function getDurationMinutes(): int
    {
        return $this->sks * 50;
    }

    /**
     * Mendapatkan jumlah slot yang dibutuhkan
     *
     * @return int Jumlah slot
     */
    public function getSlotsNeeded(): int
    {
        return $this->sks;
    }

    /**
     * Mendapatkan label lengkap dengan kode dan nama
     */
    public function getFullLabelAttribute(): string
    {
        $label = $this->name;
        if ($this->code) {
            $label = "[{$this->code}] {$label}";
        }
        return $label;
    }
}

