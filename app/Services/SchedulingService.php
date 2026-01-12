<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Laboratorium;
use App\Models\Schedule;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service untuk menangani logika penjadwalan otomatis
 * dengan constraint satisfaction problem (CSP) approach.
 *
 * Constraints yang ditangani:
 * 1. Ketersediaan Software: Lab harus memiliki semua software yang dibutuhkan
 * 2. Kapasitas Laboratorium: PC tersedia >= jumlah mahasiswa
 * 3. Ketersediaan Waktu: Slot tidak bentrok dengan jadwal lain
 * 4. Jam Operasional: Dalam rentang jam operasional lab
 */
class SchedulingService
{
    /**
     * Mendapatkan daftar lab yang tersedia untuk suatu mata kuliah
     * Filter berdasarkan: software requirements + kapasitas
     *
     * @param Course $course Mata kuliah yang akan dijadwalkan
     * @return Collection Lab yang memenuhi syarat, diurutkan berdasarkan prioritas
     */
    public function getAvailableLabs(Course $course): Collection
    {
        $requiredSoftwareIds = $course->requiredSoftware()
            ->pluck('software_details.id')
            ->toArray();

        $studentCount = $course->jumlah_mahasiswa;

        // Query dasar: lab aktif dengan kapasitas mencukupi
        $query = Laboratorium::where('is_active', true);

        // Filter kapasitas jika jumlah mahasiswa > 0
        if ($studentCount > 0) {
            $query->where('pc_siap', '>=', $studentCount);
        }

        // Filter software requirements jika ada
        if (!empty($requiredSoftwareIds)) {
            $requiredCount = count($requiredSoftwareIds);

            // Lab harus memiliki SEMUA software yang dibutuhkan
            $query->whereHas('software', function ($q) use ($requiredSoftwareIds) {
                $q->whereIn('software_details.id', $requiredSoftwareIds);
            }, '>=', $requiredCount);
        }

        // Eager load relasi yang diperlukan
        $labs = $query->with(['priorityProdis', 'kategori'])->get();

        // Sort: prioritas prodi dulu, kemudian berdasarkan nama
        return $labs->sortByDesc(function ($lab) use ($course) {
            $isPriority = $course->prodi_id
                ? $lab->priorityProdis->contains('id', $course->prodi_id)
                : false;

            return $isPriority ? 1 : 0;
        })->values();
    }

    /**
     * Mendapatkan slot waktu yang tersedia untuk lab pada hari tertentu
     *
     * @param Laboratorium $lab Lab yang dipilih
     * @param string $day Hari (Senin, Selasa, dll)
     * @param int $slotsNeeded Jumlah slot berturutan yang dibutuhkan (= SKS)
     * @param int|null $excludeScheduleId ID jadwal yang dikecualikan (untuk edit)
     * @return Collection TimeSlot yang tersedia sebagai slot awal
     */
    public function getAvailableSlots(
        Laboratorium $lab,
        string $day,
        int $slotsNeeded,
        ?int $excludeScheduleId = null
    ): Collection {
        // Ambil semua slot dalam jam operasional lab
        $operatingStart = $lab->operating_start
            ? Carbon::parse($lab->operating_start)->format('H:i:s')
            : '07:00:00';
        $operatingEnd = $lab->operating_end
            ? Carbon::parse($lab->operating_end)->format('H:i:s')
            : '21:00:00';

        $allSlots = TimeSlot::where('start_time', '>=', $operatingStart)
            ->where('end_time', '<=', $operatingEnd)
            ->orderBy('slot_number')
            ->get();

        if ($allSlots->isEmpty()) {
            return collect();
        }

        // Dapatkan slot yang sudah terisi untuk lab+hari ini
        $occupiedSlotNumbers = $this->getOccupiedSlotNumbers($lab->id, $day, $excludeScheduleId);

        // Filter slot yang bisa menjadi slot awal
        return $allSlots->filter(function ($slot) use ($allSlots, $occupiedSlotNumbers, $slotsNeeded) {
            // Cek apakah slot ini dan slot-slot berikutnya tersedia
            for ($i = 0; $i < $slotsNeeded; $i++) {
                $checkSlotNumber = $slot->slot_number + $i;

                // Pastikan slot dengan nomor tersebut ada
                $slotExists = $allSlots->contains('slot_number', $checkSlotNumber);
                if (!$slotExists) {
                    return false; // Melebihi jam operasional
                }

                // Pastikan slot tidak ditempati
                if (in_array($checkSlotNumber, $occupiedSlotNumbers)) {
                    return false; // Bentrok dengan jadwal lain
                }
            }

            return true;
        })->values();
    }

    /**
     * Mendapatkan nomor slot yang sudah terisi untuk lab+hari tertentu
     *
     * @param int $labId ID laboratorium
     * @param string $day Hari
     * @param int|null $excludeScheduleId ID jadwal yang dikecualikan
     * @return array Array of slot numbers
     */
    public function getOccupiedSlotNumbers(int $labId, string $day, ?int $excludeScheduleId = null): array
    {
        $schedules = Schedule::where('laboratorium_id', $labId)
            ->where('day', $day)
            ->when($excludeScheduleId, fn($q) => $q->where('id', '!=', $excludeScheduleId))
            ->with('timeSlot')
            ->get();

        $occupiedNumbers = [];

        foreach ($schedules as $schedule) {
            // Jika menggunakan time_slot_id (sistem baru)
            if ($schedule->time_slot_id && $schedule->timeSlot) {
                $startNumber = $schedule->timeSlot->slot_number;
                $duration = $schedule->duration_slots ?? 1;

                for ($i = 0; $i < $duration; $i++) {
                    $occupiedNumbers[] = $startNumber + $i;
                }
            }
            // Fallback: gunakan start_time/end_time (sistem lama)
            elseif ($schedule->start_time && $schedule->end_time) {
                $startTime = Carbon::parse($schedule->start_time)->format('H:i');
                $endTime = Carbon::parse($schedule->end_time)->format('H:i');

                // Cari slot berdasarkan waktu
                $slots = TimeSlot::whereRaw("TIME_FORMAT(start_time, '%H:%i') >= ?", [$startTime])
                    ->whereRaw("TIME_FORMAT(end_time, '%H:%i') <= ?", [$endTime])
                    ->pluck('slot_number')
                    ->toArray();

                $occupiedNumbers = array_merge($occupiedNumbers, $slots);
            }
        }

        return array_unique($occupiedNumbers);
    }

    /**
     * Cek apakah ada konflik jadwal
     *
     * @param int $labId ID laboratorium
     * @param string $day Hari
     * @param int $startSlotId ID TimeSlot awal
     * @param int $slotsNeeded Jumlah slot yang dibutuhkan
     * @param int|null $excludeScheduleId ID jadwal yang dikecualikan (untuk edit)
     * @return bool True jika ada konflik
     */
    public function hasConflict(
        int $labId,
        string $day,
        int $startSlotId,
        int $slotsNeeded,
        ?int $excludeScheduleId = null
    ): bool {
        $startSlot = TimeSlot::find($startSlotId);
        if (!$startSlot) {
            return true; // Invalid slot = conflict
        }

        $occupiedNumbers = $this->getOccupiedSlotNumbers($labId, $day, $excludeScheduleId);

        // Cek apakah ada overlap
        for ($i = 0; $i < $slotsNeeded; $i++) {
            if (in_array($startSlot->slot_number + $i, $occupiedNumbers)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mendapatkan rekomendasi slot untuk jadwal baru
     * Returns array of recommendations with lab + available slots
     *
     * @param Course $course Mata kuliah
     * @param string|null $preferredDay Hari yang diinginkan (opsional)
     * @return array Array of recommendations
     */
    public function getRecommendations(Course $course, ?string $preferredDay = null): array
    {
        $availableLabs = $this->getAvailableLabs($course);
        $slotsNeeded = $course->sks;
        $days = $preferredDay
            ? [$preferredDay]
            : ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

        $recommendations = [];

        foreach ($availableLabs as $lab) {
            $isPriority = $course->prodi_id
                ? $lab->priorityProdis->contains('id', $course->prodi_id)
                : false;

            foreach ($days as $day) {
                $availableSlots = $this->getAvailableSlots($lab, $day, $slotsNeeded);

                if ($availableSlots->isNotEmpty()) {
                    $recommendations[] = [
                        'lab' => $lab,
                        'day' => $day,
                        'is_priority' => $isPriority,
                        'available_slots' => $availableSlots,
                        'slot_count' => $availableSlots->count(),
                    ];
                }
            }
        }

        // Sort: prioritas prodi dulu, kemudian berdasarkan jumlah slot tersedia
        usort($recommendations, function ($a, $b) {
            if ($a['is_priority'] !== $b['is_priority']) {
                return $b['is_priority'] <=> $a['is_priority'];
            }
            return $b['slot_count'] <=> $a['slot_count'];
        });

        return $recommendations;
    }

    /**
     * Menghitung waktu selesai berdasarkan slot awal dan durasi
     *
     * @param TimeSlot $startSlot Slot awal
     * @param int $slotsNeeded Jumlah slot
     * @return string Waktu selesai format H:i
     */
    public function calculateEndTime(TimeSlot $startSlot, int $slotsNeeded): string
    {
        $startTime = Carbon::parse($startSlot->start_time);
        $durationMinutes = $slotsNeeded * 50;

        return $startTime->addMinutes($durationMinutes)->format('H:i');
    }

    /**
     * Mendapatkan opsi lab untuk form Select dengan label yang informatif
     *
     * @param Course $course
     * @return array [id => label]
     */
    public function getLabOptionsForForm(Course $course): array
    {
        $labs = $this->getAvailableLabs($course);

        return $labs->mapWithKeys(function ($lab) use ($course) {
            $isPriority = $course->prodi_id
                ? $lab->priorityProdis->contains('id', $course->prodi_id)
                : false;

            $label = $lab->ruang;

            if ($isPriority) {
                $label .= ' ⭐ (Prioritas)';
            }

            $label .= " (PC: {$lab->pc_siap})";

            if ($lab->kategori) {
                $label .= " - {$lab->kategori->nama_klasifikasi}";
            }

            return [$lab->id => $label];
        })->toArray();
    }

    /**
     * Mendapatkan opsi time slot untuk form Select
     *
     * @param Laboratorium $lab
     * @param string $day
     * @param int $slotsNeeded
     * @param int|null $excludeScheduleId
     * @return array [id => label]
     */
    public function getSlotOptionsForForm(
        Laboratorium $lab,
        string $day,
        int $slotsNeeded,
        ?int $excludeScheduleId = null
    ): array {
        $slots = $this->getAvailableSlots($lab, $day, $slotsNeeded, $excludeScheduleId);

        return $slots->mapWithKeys(function ($slot) use ($slotsNeeded) {
            $startTime = Carbon::parse($slot->start_time)->format('H:i');
            $endTime = $this->calculateEndTime($slot, $slotsNeeded);

            return [$slot->id => "{$startTime} - {$endTime}"];
        })->toArray();
    }
}
