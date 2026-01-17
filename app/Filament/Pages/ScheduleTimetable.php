<?php

namespace App\Filament\Pages;

use App\Models\Schedule;
use App\Models\Laboratorium;
use App\Exports\TimetableExport;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ScheduleTimetable extends Page implements HasActions
{
    use InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationGroup = 'Penjadwalan';

    protected static ?string $navigationLabel = 'Timetable Visual';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.schedule-timetable';

    protected static ?string $title = 'Timetable Visual';

    public ?int $selectedLabId = null;
    public array $schedulesByDay = [];

    public function mount(): void
    {
        $firstLab = Laboratorium::where('is_active', true)->first();
        if ($firstLab) {
            $this->selectedLabId = $firstLab->id;
            $this->loadSchedules();
        }
    }

    public function updatedSelectedLabId(): void
    {
        $this->loadSchedules();
    }

    /**
     * Menghasilkan array slot waktu per 50 menit dari 07:00 hingga 21:00
     * (skipping break times: 12:00-12:30, 15:50-16:20, 18:00-18:30)
     */
    public function getTimeSlots(): array
    {
        $slots = [];
        $current = Carbon::createFromTime(7, 0);
        $maxEnd = Carbon::createFromTime(21, 0);

        // Break times
        $breaks = [
            ['start' => '12:00', 'end' => '12:30'],
            ['start' => '15:50', 'end' => '16:20'],
            ['start' => '18:00', 'end' => '18:30'],
        ];

        while ($current->lt($maxEnd)) {
            $slotEnd = $current->copy()->addMinutes(50);

            // Check if slot would end after max time
            if ($slotEnd->gt($maxEnd)) {
                break;
            }

            // Check if current position is inside a break - if so, skip to break end
            $insideBreak = false;
            foreach ($breaks as $break) {
                $breakStart = Carbon::createFromFormat('H:i', $break['start']);
                $breakEnd = Carbon::createFromFormat('H:i', $break['end']);

                if ($current->gte($breakStart) && $current->lt($breakEnd)) {
                    // We're inside a break, jump to break end
                    $current = $breakEnd->copy();
                    $insideBreak = true;
                    break;
                }
            }

            if ($insideBreak) {
                continue;
            }

            // Check if slot would cross into a break
            $crossesBreak = false;
            foreach ($breaks as $break) {
                $breakStart = Carbon::createFromFormat('H:i', $break['start']);

                // If slot ends after break starts and starts before break starts
                if ($current->lt($breakStart) && $slotEnd->gt($breakStart)) {
                    // Slot would cross into break - add slot up to break start, then skip
                    $slots[] = $current->format('H:i');
                    $current = Carbon::createFromFormat('H:i', $break['end']);
                    $crossesBreak = true;
                    break;
                }
            }

            if ($crossesBreak) {
                continue;
            }

            // Normal slot
            $slots[] = $current->format('H:i');
            $current->addMinutes(50);
        }

        return $slots;
    }

    /**
     * Memuat jadwal dari database dan mengisi schedulesByDay
     */
    public function loadSchedules(): void
    {
        $this->schedulesByDay = [];

        if (!$this->selectedLabId) {
            return;
        }

        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

        // Inisialisasi array kosong untuk setiap hari
        foreach ($days as $day) {
            $this->schedulesByDay[$day] = collect();
        }

        // Ambil semua jadwal untuk laboratorium yang dipilih dengan eager loading
        $schedules = Schedule::with(['course', 'lecturer'])
            ->where('laboratorium_id', $this->selectedLabId)
            ->orderBy('start_time')
            ->get();

        // Kelompokkan jadwal berdasarkan hari
        foreach ($schedules as $schedule) {
            if (isset($this->schedulesByDay[$schedule->day])) {
                $this->schedulesByDay[$schedule->day]->push($schedule);
            }
        }
    }

    /**
     * Action untuk export seluruh jadwal ke Excel
     */
    public function exportAction(): Action
    {
        return Action::make('export')
            ->label('Export Excel')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->action(function () {
                $filename = 'Jadwal_Laboratorium_' . date('Y-m-d') . '.xlsx';
                return Excel::download(new TimetableExport(), $filename);
            });
    }

    /**
     * Header actions dengan tombol export
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->exportAction(),
        ];
    }
}
