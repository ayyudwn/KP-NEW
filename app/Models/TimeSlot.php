<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_time',
        'end_time',
        'slot_number',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    /**
     * Relasi ke Schedule yang menggunakan slot ini
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Accessor untuk label tampilan (contoh: "07:00 - 07:50")
     */
    public function getLabelAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('H:i') . ' - ' .
            Carbon::parse($this->end_time)->format('H:i');
    }

    /**
     * Accessor untuk label singkat (contoh: "07:00")
     */
    public function getShortLabelAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('H:i');
    }

    /**
     * Scope untuk mendapatkan slot dalam rentang jam operasional
     */
    public function scopeWithinOperatingHours($query, string $startTime, string $endTime)
    {
        return $query->where('start_time', '>=', $startTime)
            ->where('end_time', '<=', $endTime);
    }

    /**
     * Mendapatkan slot berdasarkan waktu mulai
     */
    public static function findByStartTime(string $time): ?self
    {
        return self::whereRaw("TIME_FORMAT(start_time, '%H:%i') = ?", [$time])->first();
    }
}
