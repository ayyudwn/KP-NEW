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
     */
    public function getTimeSlots(): array
    {
        $slots = [];
        $start = Carbon::createFromTime(7, 0);
        $end = Carbon::createFromTime(21, 0);

        while ($start->lessThan($end)) {
            $slots[] = $start->format('H:i');
            $start->addMinutes(50);
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
