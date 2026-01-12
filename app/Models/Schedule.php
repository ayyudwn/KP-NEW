<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    protected $fillable = [
        'course_id',
        'lecturer_id',
        'laboratorium_id',
        'time_slot_id',
        'duration_slots',
        'kelompok',
        'day',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    public function laboratorium(): BelongsTo
    {
        return $this->belongsTo(Laboratorium::class);
    }

    /**
     * Relasi ke TimeSlot
     */
    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    /**
     * Mendapatkan semua slot ID yang ditempati oleh jadwal ini
     *
     * @return array Array of TimeSlot IDs
     */
    public function getOccupiedSlotIds(): array
    {
        if (!$this->time_slot_id) {
            return [];
        }

        $startSlot = $this->timeSlot;
        if (!$startSlot) {
            return [];
        }

        $startSlotNumber = $startSlot->slot_number;
        $endSlotNumber = $startSlotNumber + ($this->duration_slots ?? 1) - 1;

        return TimeSlot::whereBetween('slot_number', [$startSlotNumber, $endSlotNumber])
            ->pluck('id')
            ->toArray();
    }

    /**
     * Mendapatkan semua slot number yang ditempati
     *
     * @return array Array of slot numbers
     */
    public function getOccupiedSlotNumbers(): array
    {
        if (!$this->time_slot_id) {
            return [];
        }

        $startSlot = $this->timeSlot;
        if (!$startSlot) {
            return [];
        }

        $startNumber = $startSlot->slot_number;
        $duration = $this->duration_slots ?? 1;

        return range($startNumber, $startNumber + $duration - 1);
    }

    /**
     * Mendapatkan label waktu lengkap
     *
     * @return string Format: "07:00 - 08:30"
     */
    public function getTimeLabelAttribute(): string
    {
        if ($this->start_time && $this->end_time) {
            return Carbon::parse($this->start_time)->format('H:i') . ' - ' .
                Carbon::parse($this->end_time)->format('H:i');
        }

        if ($this->timeSlot) {
            $startTime = Carbon::parse($this->timeSlot->start_time);
            $endTime = $startTime->copy()->addMinutes(($this->duration_slots ?? 1) * 50);
            return $startTime->format('H:i') . ' - ' . $endTime->format('H:i');
        }

        return '-';
    }

    /**
     * Mendapatkan label lengkap jadwal
     */
    public function getFullLabelAttribute(): string
    {
        $courseName = $this->course?->name ?? 'N/A';
        $labName = $this->laboratorium?->ruang ?? 'N/A';
        return "{$courseName} - {$this->day} ({$this->time_label}) @ {$labName}";
    }

    /**
     * Sync time_slot_id with start_time/end_time
     * Call this after setting time_slot_id to keep legacy columns in sync
     */
    public function syncTimeWithSlot(): void
    {
        if ($this->time_slot_id && $this->timeSlot) {
            $this->start_time = Carbon::parse($this->timeSlot->start_time)->format('H:i:s');

            // Calculate end time based on duration_slots
            $startTime = Carbon::parse($this->timeSlot->start_time);
            $durationMinutes = ($this->duration_slots ?? 1) * 50;
            $this->end_time = $startTime->addMinutes($durationMinutes)->format('H:i:s');
        }
    }
}

