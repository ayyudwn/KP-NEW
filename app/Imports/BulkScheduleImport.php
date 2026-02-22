<?php

namespace App\Imports;

use App\Models\Course;
use App\Models\Laboratorium;
use App\Models\Prodi;
use App\Models\Schedule;
use App\Models\TimeSlot;
use App\Services\SchedulingService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BulkScheduleImport implements ToCollection, WithHeadingRow
{
    public array $results = [];
    public array $unplottedSchedules = [];
    public int $totalGenerated = 0;
    public int $successCount = 0;
    public int $warningCount = 0;
    public int $errorCount = 0;

    // Time slot configuration
    // 1 SKS = 50 minutes
    private const MINUTES_PER_SKS = 50;

    // Available start times for sessions
    // Based on migration: each slot is 50 min with breaks at 12:00, 15:50, 18:00
    // NOTE: For this Excel import case, "pagi" includes pagi+siang (07:00-18:00)
    private array $pagiStartTimes = [
        // Pagi: 07:00 - 12:00 (5 slots × 50min)
        '07:00',
        '07:50',
        '08:40',
        '09:30',
        '10:20',
        '11:10',
        // Siang: 12:30 - 15:50 (4 slots × 50min)
        '12:30',
        '13:20',
        '14:10',
        '15:00',
        // After shifted break for 3+ SKS siang (15:00-15:30)
        '15:30',
        // Sore: 16:20 - 18:00 (2 slots × 50min)
        '16:20',
        '17:10',
    ];

    private array $malamStartTimes = [
        // Malam: 18:30 - 21:00 (3 slots × 50min)
        '18:30',
        '19:20',
        '20:10',
    ];

    private array $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

    // Temporary storage for schedule data before sorting
    private array $pendingSchedules = [];

    public function collection(Collection $rows): void
    {
        // First pass: collect all schedule data
        foreach ($rows as $row) {
            $this->collectScheduleData($row);
        }

        // Sort by SKS DESC - higher SKS gets priority for lab assignment
        usort($this->pendingSchedules, function ($a, $b) {
            return $b['sks'] <=> $a['sks'];
        });

        // Second pass: process sorted schedules
        foreach ($this->pendingSchedules as $scheduleData) {
            $this->generateSchedule($scheduleData);
        }
    }

    /**
     * Collect schedule data from row without processing
     */
    private function collectScheduleData($row): void
    {
        $prodiCode = strtoupper(trim($row['prodi'] ?? ''));
        $kdmk = strtoupper(trim($row['kdmk'] ?? ''));
        $namaMk = strtoupper(trim($row['nama_mk'] ?? $row['nama'] ?? ''));
        $pagiCount = (int) ($row['pagi'] ?? 0);
        $malamCount = (int) ($row['malam'] ?? 0);
        $sks = $this->parseSks($row['sks'] ?? '2');

        if (empty($kdmk) && empty($namaMk)) {
            return; // Skip empty rows
        }

        // Find prodi (handle potential whitespace in database)
        $prodi = Prodi::whereRaw('TRIM(code) = ?', [$prodiCode])->first();

        // Find course
        $course = Course::where('code', $kdmk)->first();
        if (!$course && !empty($namaMk)) {
            $course = Course::whereRaw('LOWER(name) = ?', [strtolower($namaMk)])->first();
        }

        // Collect schedules for pagi
        for ($i = 1; $i <= $pagiCount; $i++) {
            $this->pendingSchedules[] = [
                'prodi' => $prodi,
                'prodi_code' => $prodiCode,
                'course' => $course,
                'kdmk' => $kdmk,
                'nama_mk' => $namaMk,
                'kelompok' => "{$kdmk}-" . str_pad($i, 2, '0', STR_PAD_LEFT),
                'sesi' => 'pagi',
                'sks' => $sks,
                'class_number' => $i,
            ];
        }

        // Collect schedules for malam
        for ($i = 1; $i <= $malamCount; $i++) {
            $this->pendingSchedules[] = [
                'prodi' => $prodi,
                'prodi_code' => $prodiCode,
                'course' => $course,
                'kdmk' => $kdmk,
                'nama_mk' => $namaMk,
                'kelompok' => "{$kdmk}-M" . str_pad($i, 2, '0', STR_PAD_LEFT),
                'sesi' => 'malam',
                'sks' => $sks,
                'class_number' => $pagiCount + $i,
            ];
        }
    }

    private function generateSchedule(array $data): void
    {
        $this->totalGenerated++;

        // Get start times based on session
        $startTimes = $data['sesi'] === 'pagi' ? $this->pagiStartTimes : $this->malamStartTimes;

        // Try to find available lab and slot (pass SKS for duration calculation)
        $assignment = $this->findAvailableSlot($data, $startTimes, $data['sks']);

        // Check SKS mismatch between Excel and database
        $sksMismatch = false;
        $dbSks = null;
        if ($data['course']) {
            $dbSks = $data['course']->sks;
            if ($dbSks != $data['sks']) {
                $sksMismatch = true;
            }
        }

        $result = [
            'prodi_code' => $data['prodi_code'],
            'kdmk' => $data['kdmk'],
            'nama_mk' => $data['nama_mk'],
            'kelompok' => $data['kelompok'],
            'sesi' => $data['sesi'],
            'sks' => $data['sks'],
            'sks_db' => $dbSks, // SKS from database for comparison
            'course_id' => $data['course']?->id,
            'status' => 'pending',
            'message' => '',
        ];

        if ($assignment) {
            $result['laboratorium_id'] = $assignment['lab']->id;
            $result['laboratorium_name'] = $assignment['lab']->ruang;
            $result['is_priority'] = $assignment['is_priority'] ?? false;
            $result['day'] = $assignment['day'];
            $result['start_time'] = $assignment['start_time'];
            $result['end_time'] = $assignment['end_time'];
            $result['time_slot_id'] = $assignment['time_slot_id'];

            if ($data['course']) {
                if ($sksMismatch) {
                    $result['status'] = 'warning';
                    $result['message'] = "SKS tidak cocok: Excel={$data['sks']}, Database={$dbSks}";
                    $this->warningCount++;
                } else {
                    $result['status'] = 'ok';
                    $result['message'] = 'Siap import';
                    $this->successCount++;
                }
            } else {
                $result['status'] = 'warning';
                $result['message'] = 'Matkul tidak ditemukan di database';
                $this->warningCount++;
            }
        } else {
            $result['status'] = 'error';
            $result['message'] = $this->lastFailureReason ?: 'Tidak ada slot tersedia';
            $result['laboratorium_id'] = null;
            $result['laboratorium_name'] = '-';
            $result['day'] = '-';
            $result['start_time'] = '-';
            $result['end_time'] = '-';
            $this->errorCount++;
            $this->unplottedSchedules[] = $result;
        }

        $this->results[] = $result;
    }

    // Break times are now dynamic via SchedulingService::getBreakTimes()

    // Track failure reason for current search
    private string $lastFailureReason = '';

    private function findAvailableSlot(array $data, array $startTimes, int $sks): ?array
    {
        // Get labs sorted by priority
        $labs = $this->getLabsByPriority($data['prodi']);

        // Distribute evenly across days
        $dayOrder = $this->getDayOrder();

        // Calculate duration based on SKS (1 SKS = 50 minutes)
        $durationMinutes = $sks * self::MINUTES_PER_SKS;

        // Track failure reasons
        $crossesBreakCount = 0;
        $labFullCount = 0;
        $totalCheckedSlots = 0;

        foreach ($dayOrder as $day) {
            foreach ($startTimes as $startTime) {
                // Calculate end time based on SKS
                $endTime = $this->calculateEndTime($startTime, $durationMinutes);

                // IMPORTANT: Check if end time crosses a break
                // Determine sesi for dynamic break calculation
                $startHour = (int) explode(':', $startTime)[0];
                $startMin = (int) explode(':', $startTime)[1];
                $startInMinutes = $startHour * 60 + $startMin;
                $currentSesi = $startInMinutes < 12 * 60 + 30 ? 'pagi' : ($startInMinutes < 18 * 60 + 30 ? 'siang' : 'malam');

                if ($this->crossesBreak($startTime, $endTime, $sks, $currentSesi)) {
                    $crossesBreakCount++;
                    continue; // Skip this slot, try next one
                }

                $totalCheckedSlots++;

                foreach ($labs as $lab) {
                    if ($this->isSlotAvailable($lab->id, $day, $startTime, $endTime, $sks)) {
                        // Find time_slot_id
                        $timeSlot = TimeSlot::where('start_time', $startTime)->first();

                        // Mark slot as used (in memory for this import session)
                        // For multi-slot schedules, mark all covered slots
                        $this->markSlotUsed($lab->id, $day, $startTime, $sks);

                        // Check if this lab is priority for the prodi
                        $isPriority = $data['prodi'] ? $lab->isPriorityFor($data['prodi']->id) : false;

                        $this->lastFailureReason = '';
                        return [
                            'lab' => $lab,
                            'day' => $day,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'time_slot_id' => $timeSlot?->id,
                            'is_priority' => $isPriority,
                        ];
                    } else {
                        $labFullCount++;
                    }
                }
            }
        }

        // Determine main failure reason
        if ($labs->isEmpty()) {
            $this->lastFailureReason = 'Tidak ada lab aktif';
        } elseif ($totalCheckedSlots == 0 && $crossesBreakCount > 0) {
            $this->lastFailureReason = "SKS {$sks} ({$durationMinutes} menit) melewati break di semua slot";
        } elseif ($labFullCount > 0) {
            $this->lastFailureReason = "Semua {$labs->count()} lab penuh untuk {$sks} slot berturutan";
        } else {
            $this->lastFailureReason = 'Tidak ada slot tersedia';
        }

        return null;
    }

    /**
     * Get the last failure reason
     */
    public function getLastFailureReason(): string
    {
        return $this->lastFailureReason;
    }

    /**
     * Check if a time range crosses any break period
     * Uses dynamic breaks based on SKS and sesi
     */
    private function crossesBreak(string $startTime, string $endTime, int $sks = 2, string $sesi = 'pagi'): bool
    {
        $start = \Carbon\Carbon::createFromFormat('H:i', $startTime);
        $end = \Carbon\Carbon::createFromFormat('H:i', $endTime);

        $breakTimes = SchedulingService::getBreakTimes($sks, $sesi);

        foreach ($breakTimes as $breakTime) {
            $breakStart = \Carbon\Carbon::createFromFormat('H:i', $breakTime['start']);
            // If break start is between start and end (exclusive of start, inclusive of end)
            if ($breakStart->gt($start) && $breakStart->lte($end)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate end time based on start time and duration
     * @param string $startTime Format H:i
     * @param int $durationMinutes Duration in minutes
     * @return string End time in H:i format
     */
    private function calculateEndTime(string $startTime, int $durationMinutes): string
    {
        $start = \Carbon\Carbon::createFromFormat('H:i', $startTime);
        return $start->addMinutes($durationMinutes)->format('H:i');
    }

    private function getLabsByPriority(?Prodi $prodi): Collection
    {
        $labs = Laboratorium::active()->get();

        if (!$prodi) {
            return $labs;
        }

        // Sort: priority labs first, then others
        return $labs->sortBy(function ($lab) use ($prodi) {
            if ($lab->isPriorityFor($prodi->id)) {
                return 0; // Priority labs first
            }
            return 1;
        });
    }

    // Track used slot_numbers per lab+day (key: "labId_day" => [slot_numbers])
    private array $usedSlotNumbers = [];

    /**
     * Check if slot is available - both in DB and in-memory
     * Uses slot_number system like SchedulingService for accurate conflict detection
     */
    private function isSlotAvailable(int $labId, string $day, string $startTime, string $endTime, int $sks): bool
    {
        $slotsNeeded = $sks; // 1 SKS = 1 slot (50 min)

        // Find the TimeSlot for this start time
        $startSlot = TimeSlot::whereRaw("TIME_FORMAT(start_time, '%H:%i') = ?", [$startTime])->first();
        if (!$startSlot) {
            return false; // Cannot find slot
        }

        $startSlotNumber = $startSlot->slot_number;
        $key = "{$labId}_{$day}";

        // Initialize if not exists
        if (!isset($this->usedSlotNumbers[$key])) {
            $this->usedSlotNumbers[$key] = $this->getOccupiedSlotNumbersFromDB($labId, $day);
        }

        // Check if any of the needed slots are occupied
        for ($i = 0; $i < $slotsNeeded; $i++) {
            $checkSlotNumber = $startSlotNumber + $i;
            if (in_array($checkSlotNumber, $this->usedSlotNumbers[$key])) {
                return false; // Slot already occupied
            }
        }

        return true;
    }

    /**
     * Get occupied slot numbers from database for a lab+day
     */
    private function getOccupiedSlotNumbersFromDB(int $labId, string $day): array
    {
        $schedules = Schedule::where('laboratorium_id', $labId)
            ->where('day', $day)
            ->with('timeSlot')
            ->get();

        $occupiedNumbers = [];

        foreach ($schedules as $schedule) {
            // If using time_slot_id (new system)
            if ($schedule->time_slot_id && $schedule->timeSlot) {
                $startNumber = $schedule->timeSlot->slot_number;
                $duration = $schedule->duration_slots ?? 1;

                for ($i = 0; $i < $duration; $i++) {
                    $occupiedNumbers[] = $startNumber + $i;
                }
            }
            // Fallback: use start_time/end_time (legacy)
            elseif ($schedule->start_time && $schedule->end_time) {
                $startTime = \Carbon\Carbon::parse($schedule->start_time)->format('H:i');
                $endTime = \Carbon\Carbon::parse($schedule->end_time)->format('H:i');

                // Find slots that overlap
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
     * Mark slots as used in memory - marks ALL slots covered by duration
     */
    private function markSlotUsed(int $labId, string $day, string $startTime, int $sks): void
    {
        $slotsNeeded = $sks; // 1 SKS = 1 slot (50 min)

        // Find the TimeSlot for this start time
        $startSlot = TimeSlot::whereRaw("TIME_FORMAT(start_time, '%H:%i') = ?", [$startTime])->first();
        if (!$startSlot) {
            return;
        }

        $startSlotNumber = $startSlot->slot_number;
        $key = "{$labId}_{$day}";

        // Initialize if not exists
        if (!isset($this->usedSlotNumbers[$key])) {
            $this->usedSlotNumbers[$key] = [];
        }

        // Mark ALL slots covered by this schedule as used
        for ($i = 0; $i < $slotsNeeded; $i++) {
            $this->usedSlotNumbers[$key][] = $startSlotNumber + $i;
        }
    }

    private array $dayDistribution = [];

    private function getDayOrder(): array
    {
        // Sort days by usage count for even distribution
        if (empty($this->dayDistribution)) {
            $this->dayDistribution = array_fill_keys($this->days, 0);
        }

        $days = $this->days;
        usort($days, function ($a, $b) {
            return ($this->dayDistribution[$a] ?? 0) <=> ($this->dayDistribution[$b] ?? 0);
        });

        // Increment the first day's count
        if (!empty($days)) {
            $this->dayDistribution[$days[0]]++;
        }

        return $days;
    }

    private function parseSks(string $sks): int
    {
        // Parse "2 sks" -> 2
        preg_match('/(\d+)/', $sks, $matches);
        return (int) ($matches[1] ?? 2);
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function getUnplottedSchedules(): array
    {
        return $this->unplottedSchedules;
    }

    public function getSummary(): array
    {
        return [
            'total' => $this->totalGenerated,
            'success' => $this->successCount,
            'warning' => $this->warningCount,
            'error' => $this->errorCount,
        ];
    }
}
