<?php

namespace App\Filament\Pages;

use App\Models\Schedule;
use App\Models\Laboratorium;
use App\Models\Course;
use App\Exports\TimetableExport;
use App\Imports\LabScheduleSheetImport;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Livewire\WithFileUploads;

class ScheduleTimetable extends Page implements HasActions
{
    use InteractsWithActions;
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationGroup = 'Penjadwalan';

    protected static ?string $navigationLabel = 'Timetable Visual';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.schedule-timetable';

    protected static ?string $title = 'Timetable Visual';

    public ?int $selectedLabId = null;
    public array $schedulesByDay = [];

    // Import state
    public bool $showImportPreview = false;
    public array $importResults = [];
    public $uploadedFile = null;
    public array $courseMapping = [];

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
     * Break sore dinamis: jika ada jadwal 3+ SKS siang di lab ini, break digeser ke 15:00-15:30
     */
    public function getTimeSlots(): array
    {
        $breaks = $this->getBreaksForCurrentLab();
        return \App\Services\SchedulingService::generateTimeSlots($breaks);
    }

    /**
     * Determine break times based on whether any 3+ SKS siang schedule exists in current lab
     */
    protected function getBreaksForCurrentLab(): array
    {
        if (!$this->selectedLabId) {
            return \App\Services\SchedulingService::DEFAULT_BREAKS;
        }

        // Check if any schedule in this lab has a 3+ SKS siang course
        $has3SksSiang = Schedule::where('laboratorium_id', $this->selectedLabId)
            ->whereHas('course', function ($q) {
                $q->where('sks', '>=', 3);
            })
            ->where('sesi', 'siang')
            ->exists();

        return \App\Services\SchedulingService::getBreakTimes(
            $has3SksSiang ? 3 : 2,
            $has3SksSiang ? 'siang' : null
        );
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

        foreach ($days as $day) {
            $this->schedulesByDay[$day] = collect();
        }

        $schedules = Schedule::with(['course', 'lecturer'])
            ->where('laboratorium_id', $this->selectedLabId)
            ->orderBy('start_time')
            ->get();

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
     * Action untuk import jadwal dari Excel
     */
    public function importAction(): Action
    {
        return Action::make('import')
            ->label('Import Excel')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('warning')
            ->form([
                FileUpload::make('file')
                    ->label('Upload File Excel')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->required()
                    ->helperText('Format file harus sama dengan hasil export (.xlsx)'),
            ])
            ->action(function (array $data) {
                $this->processImportPreview($data['file']);
            });
    }

    /**
     * Process uploaded file and show preview
     */
    public function processImportPreview($filePath): void
    {
        $fullPath = storage_path('app/public/' . $filePath);

        if (!file_exists($fullPath)) {
            Notification::make()
                ->title('File tidak ditemukan')
                ->danger()
                ->send();
            return;
        }

        $this->importResults = [];

        // Get sheet names from Excel file
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
        $sheetNames = $spreadsheet->getSheetNames();

        // Get all active labs
        $labs = Laboratorium::where('is_active', true)->get()->keyBy('ruang');

        // Read each sheet that matches a lab name
        foreach ($sheetNames as $sheetIndex => $sheetName) {
            $lab = $labs->get($sheetName);
            if (!$lab) {
                continue; // Skip sheets that don't match a lab
            }

            try {
                $import = new LabScheduleSheetImport($lab, true);

                // Read only this specific sheet
                $worksheet = $spreadsheet->getSheet($sheetIndex);
                $rows = $worksheet->toArray(null, true, true, false);

                // Convert to collection and process
                $import->processRows(collect($rows));
                $results = $import->getResults();

                if (!empty($results)) {
                    $this->importResults[$lab->ruang] = $results;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        if (empty($this->importResults)) {
            Notification::make()
                ->title('Tidak ada data valid ditemukan')
                ->body('Pastikan nama sheet sesuai dengan nama laboratorium')
                ->warning()
                ->send();
            return;
        }

        $this->showImportPreview = true;

        Notification::make()
            ->title('Preview Import')
            ->body('Periksa data di bawah sebelum melakukan import')
            ->info()
            ->send();
    }

    /**
     * Update course mapping for a specific row
     */
    public function updateCourseMapping(string $labName, int $rowIndex, ?int $courseId): void
    {
        $this->courseMapping["{$labName}_{$rowIndex}"] = $courseId;

        // Update the result status
        if (isset($this->importResults[$labName][$rowIndex])) {
            if ($courseId) {
                $course = Course::find($courseId);
                $this->importResults[$labName][$rowIndex]['course_id'] = $courseId;
                $this->importResults[$labName][$rowIndex]['course_name'] = $course?->name;
                // Remove course error
                $this->importResults[$labName][$rowIndex]['errors'] = array_filter(
                    $this->importResults[$labName][$rowIndex]['errors'],
                    fn($e) => !str_contains($e, 'Mata kuliah tidak ditemukan')
                );
                // Update status
                if (empty($this->importResults[$labName][$rowIndex]['errors'])) {
                    $this->importResults[$labName][$rowIndex]['status'] =
                        empty($this->importResults[$labName][$rowIndex]['warnings']) ? 'valid' : 'warning';
                }
            }
        }
    }

    /**
     * Confirm and execute import
     */
    public function confirmImport(): void
    {
        $totalImported = 0;
        $totalSkipped = 0;

        $labs = Laboratorium::where('is_active', true)->get()->keyBy('ruang');

        foreach ($this->importResults as $labName => $results) {
            $lab = $labs->get($labName);
            if (!$lab)
                continue;

            foreach ($results as $result) {
                // Skip rows with errors
                if ($result['status'] === 'error') {
                    $totalSkipped++;
                    continue;
                }

                // Get course ID (can be null if course not found - will be assigned later)
                $courseId = $result['course_id'] ?? null;

                // Only skip if start_time is invalid
                if (!$result['start_time']) {
                    $totalSkipped++;
                    continue;
                }

                // Create lecturer if needed
                $lecturerId = $result['lecturer_id'] ?? null;
                if (!empty($result['create_lecturer']) && !empty($result['new_lecturer_name'])) {
                    $lecturer = \App\Models\Lecturer::firstOrCreate(
                        ['name' => $result['new_lecturer_name']]
                    );
                    $lecturerId = $lecturer->id;
                }

                // Check for duplicate before creating
                $exists = Schedule::where('laboratorium_id', $lab->id)
                    ->where('day', $result['day'])
                    ->where('start_time', $result['start_time'])
                    ->exists();

                if ($exists) {
                    $totalSkipped++;
                    continue;
                }

                // Create schedule
                // Determine sesi based on start_time
                $startHour = (int) substr($result['start_time'], 0, 2);
                $startMinute = (int) substr($result['start_time'], 3, 2);
                $startInMinutes = $startHour * 60 + $startMinute;

                if ($startInMinutes < 12 * 60 + 30) { // Before 12:30
                    $sesi = 'pagi';
                } elseif ($startInMinutes < 18 * 60 + 30) { // Before 18:30
                    $sesi = 'siang';
                } else {
                    $sesi = 'malam';
                }

                // Find matching time_slot_id based on start_time
                $timeSlot = \App\Models\TimeSlot::whereRaw("TIME_FORMAT(start_time, '%H:%i') = ?", [$result['start_time']])
                    ->first();

                Schedule::create([
                    'course_id' => $courseId,
                    'lecturer_id' => $lecturerId,
                    'laboratorium_id' => $lab->id,
                    'day' => $result['day'],
                    'start_time' => $result['start_time'],
                    'end_time' => $result['end_time'],
                    'kelompok' => $result['kelompok'] ?? null,
                    'sesi' => $sesi,
                    'time_slot_id' => $timeSlot?->id,
                ]);

                $totalImported++;
            }
        }

        $this->showImportPreview = false;
        $this->importResults = [];
        $this->loadSchedules();

        Notification::make()
            ->title('Import Selesai')
            ->body("Berhasil import {$totalImported} jadwal. {$totalSkipped} dilewati.")
            ->success()
            ->send();
    }

    /**
     * Cancel import
     */
    public function cancelImport(): void
    {
        $this->showImportPreview = false;
        $this->importResults = [];
        $this->courseMapping = [];
    }

    /**
     * Get all courses for dropdown
     */
    public function getAllCourses(): array
    {
        return Course::orderBy('name')
            ->get()
            ->mapWithKeys(fn($c) => [$c->id => "[{$c->code}] {$c->name}"])
            ->toArray();
    }

    /**
     * Header actions dengan tombol export dan import
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->exportAction(),
            $this->importAction(),
        ];
    }
}
