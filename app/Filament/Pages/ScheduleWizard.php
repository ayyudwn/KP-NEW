<?php

namespace App\Filament\Pages;

use App\Imports\BulkScheduleImport;
use App\Models\Course;
use App\Models\Laboratorium;
use App\Models\Lecturer;
use App\Models\Schedule;
use App\Models\TimeSlot;
use App\Services\SchedulingService;
use Carbon\Carbon;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

class ScheduleWizard extends Page implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static string $view = 'filament.pages.schedule-wizard';

    protected static ?string $slug = 'schedule-wizard';

    protected static ?string $navigationGroup = 'Penjadwalan';

    protected static ?string $navigationLabel = 'Penjadwalan Otomatis';

    protected static ?string $title = 'Penjadwalan Otomatis';

    protected static ?int $navigationSort = 3;

    // Form state - these are bound directly to wire:model
    public ?array $data = [];

    // UI state
    public bool $showRecommendations = false;
    public array $recommendations = [];
    public ?string $selectedDay = null;

    // Import state
    public bool $showImportModal = false;
    public bool $showImportPreview = false;
    public $importFile = null;
    public array $importResults = [];
    public array $importSummary = [];
    public array $unplottedSchedules = [];
    public int $previewPage = 1;
    public int $perPage = 50;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Input Jadwal')
                    ->description('Isi data mata kuliah, dosen, kelompok, jumlah siswa, dan sesi waktu')
                    ->schema([
                        Select::make('prodi_id')
                            ->label('Program Studi')
                            ->options(\App\Models\Prodi::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, \Filament\Forms\Set $set) {
                                // Reset course when prodi changes
                                $set('course_id', null);
                                $set('kelompok_code', null);
                                $set('kelompok', null);
                                $this->resetRecommendations();
                            })
                            ->helperText('Pilih program studi terlebih dahulu'),

                        Select::make('course_id')
                            ->label('Mata Kuliah')
                            ->options(function (\Filament\Forms\Get $get) {
                                $prodiId = $get('prodi_id');
                                if (!$prodiId) {
                                    return [];
                                }
                                return Course::where('prodi_id', $prodiId)
                                    ->get()
                                    ->mapWithKeys(function ($course) {
                                        $label = $course->name;
                                        if ($course->code) {
                                            $label = "[{$course->code}] " . $label;
                                        }
                                        $label .= " - {$course->sks} SKS";
                                        return [$course->id => $label];
                                    });
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->disabled(fn(\Filament\Forms\Get $get) => !$get('prodi_id'))
                            ->helperText(fn(\Filament\Forms\Get $get) => !$get('prodi_id') ? 'Pilih prodi terlebih dahulu' : null)
                            ->afterStateUpdated(function ($state, \Filament\Forms\Set $set) {
                                $this->resetRecommendations();
                                // Reset kelompok when course changes
                                $set('kelompok_code', null);
                                $set('kelompok', null);
                            })
                            ->columnSpan(2),

                        Select::make('lecturer_id')
                            ->label('Dosen Pengampu')
                            ->options(Lecturer::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Nama Dosen')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data) {
                                return Lecturer::create($data)->id;
                            }),

                        TextInput::make('jumlah_siswa')
                            ->label('Jumlah Siswa')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->placeholder('30')
                            ->helperText('Jumlah mahasiswa yang mengikuti kelas'),

                        TextInput::make('kelompok_code')
                            ->label('Kode Kelompok/Kelas')
                            ->placeholder('0001')
                            ->maxLength(20)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, \Filament\Forms\Set $set, \Filament\Forms\Get $get) {
                                // Generate kelompok lengkap dari prodi code + input
                                $courseId = $get('course_id');
                                if ($courseId && $state) {
                                    $course = Course::with('prodi')->find($courseId);
                                    if ($course && $course->prodi && $course->prodi->code) {
                                        $set('kelompok', $course->prodi->code . '.' . $state);
                                    } else {
                                        $set('kelompok', $state);
                                    }
                                } else {
                                    $set('kelompok', $state);
                                }
                            })
                            ->helperText(function (\Filament\Forms\Get $get) {
                                $courseId = $get('course_id');
                                if ($courseId) {
                                    $course = Course::with('prodi')->find($courseId);
                                    if ($course && $course->prodi && $course->prodi->code) {
                                        return "Kode prodi: {$course->prodi->code}";
                                    }
                                }
                                return "Pilih matkul dulu";
                            }),

                        TextInput::make('kelompok')
                            ->label('Kelompok (Otomatis)')
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('A11.0001')
                            ->helperText('KodeProdi.KodeKelompok'),

                        Select::make('sesi')
                            ->label('Sesi Waktu')
                            ->options([
                                'pagi' => '🌅 Pagi (mulai 07:00)',
                                'siang' => '☀️ Siang (mulai 12:30)',
                                'malam' => '🌙 Malam (mulai 18:30)',
                            ])
                            ->required()
                            ->helperText('Pilih sesi waktu perkuliahan'),
                    ])
                    ->columns(6),
            ])
            ->statePath('data');
    }

    /**
     * Reset recommendations when form changes
     */
    public function resetRecommendations(): void
    {
        $this->showRecommendations = false;
        $this->recommendations = [];
        $this->selectedDay = null;
    }

    /**
     * Generate schedule recommendations
     */
    public function findAvailableSlots(): void
    {
        $courseId = $this->data['course_id'] ?? null;
        $jumlahSiswa = $this->data['jumlah_siswa'] ?? null;
        $sesi = $this->data['sesi'] ?? null;

        if (!$courseId) {
            Notification::make()
                ->title('Pilih mata kuliah terlebih dahulu')
                ->warning()
                ->send();
            return;
        }

        if (!$jumlahSiswa) {
            Notification::make()
                ->title('Masukkan jumlah siswa terlebih dahulu')
                ->warning()
                ->send();
            return;
        }

        if (!$sesi) {
            Notification::make()
                ->title('Pilih sesi waktu terlebih dahulu')
                ->warning()
                ->send();
            return;
        }

        $course = Course::with(['prodi', 'requiredSoftware'])->find($courseId);
        if (!$course) {
            Notification::make()
                ->title('Mata kuliah tidak ditemukan')
                ->danger()
                ->send();
            return;
        }

        // Define session time ranges
        $sessionTimes = [
            'pagi' => ['start' => '07:00', 'end' => '12:20'],   // Pagi: 07:00 - sebelum 12:30
            'siang' => ['start' => '12:30', 'end' => '18:20'], // Siang: 12:30 - sebelum 18:30
            'malam' => ['start' => '18:30', 'end' => '22:00'], // Malam: 18:30 - 22:00
        ];

        $sessionRange = $sessionTimes[$sesi] ?? $sessionTimes['pagi'];

        $service = app(SchedulingService::class);
        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

        $this->recommendations = [];

        // Get all labs with enough capacity for jumlah_siswa
        $availableLabs = Laboratorium::where('is_active', true)
            ->where('pc_siap', '>=', $jumlahSiswa)
            ->with(['priorityProdis', 'kategori'])
            ->get();

        // Filter labs that have required software (from inventory)
        $requiredSoftwareIds = $course->requiredSoftware()->pluck('software_details.id')->toArray();
        if (!empty($requiredSoftwareIds)) {
            $requiredCount = count($requiredSoftwareIds);
            $availableLabs = $availableLabs->filter(function ($lab) use ($requiredSoftwareIds, $requiredCount) {
                // Get software IDs from lab's inventory (not from lab_software pivot)
                $labSoftwareIds = \App\Models\Inventory::where('laboratorium_id', $lab->id)
                    ->where('inventoriable_type', \App\Models\SoftwareDetail::class)
                    ->pluck('inventoriable_id')
                    ->toArray();
                $matchCount = count(array_intersect($requiredSoftwareIds, $labSoftwareIds));
                return $matchCount >= $requiredCount;
            });
        }

        foreach ($days as $day) {
            $dayRecommendations = [];

            foreach ($availableLabs as $lab) {
                // Get available slots filtered by session time
                $availableSlots = $service->getAvailableSlots($lab, $day, $course->sks);

                // Filter slots by session time range
                $filteredSlots = $availableSlots->filter(function ($slot) use ($sessionRange) {
                    $slotStartTime = Carbon::parse($slot->start_time)->format('H:i');
                    return $slotStartTime >= $sessionRange['start'] && $slotStartTime < $sessionRange['end'];
                });

                // Use dynamic breaks based on SKS + sesi
                $breakTimes = SchedulingService::getBreakTimes($course->sks, $sesi);
                $maxEndTime = '21:00';

                // Filter out slots that overlap with break times or exceed max end time
                $filteredSlots = $filteredSlots->filter(function ($slot) use ($service, $course, $breakTimes, $maxEndTime) {
                    $slotStart = Carbon::parse($slot->start_time)->format('H:i');
                    $slotEnd = $service->calculateEndTime($slot, $course->sks);

                    // Check if slot ends after max time
                    if ($slotEnd > $maxEndTime) {
                        return false;
                    }

                    // Check if slot overlaps with any break time
                    foreach ($breakTimes as $break) {
                        // Slot overlaps if: slot starts before break ends AND slot ends after break starts
                        if ($slotStart < $break['end'] && $slotEnd > $break['start']) {
                            return false;
                        }
                    }

                    return true;
                });

                if ($filteredSlots->isNotEmpty()) {
                    $isPriority = $course->prodi_id
                        ? $lab->priorityProdis->contains('id', $course->prodi_id)
                        : false;

                    foreach ($filteredSlots as $slot) {
                        $endTime = $service->calculateEndTime($slot, $course->sks);

                        $dayRecommendations[] = [
                            'lab_id' => $lab->id,
                            'lab_name' => $lab->ruang,
                            'lab_capacity' => $lab->pc_siap,
                            'is_priority' => $isPriority,
                            'slot_id' => $slot->id,
                            'start_time' => Carbon::parse($slot->start_time)->format('H:i'),
                            'end_time' => $endTime,
                            'slot_number' => $slot->slot_number,
                        ];
                    }
                }
            }

            // Sort: priority first, then by start time
            usort($dayRecommendations, function ($a, $b) {
                if ($a['is_priority'] !== $b['is_priority']) {
                    return $b['is_priority'] <=> $a['is_priority'];
                }
                return $a['slot_number'] <=> $b['slot_number'];
            });

            $this->recommendations[$day] = $dayRecommendations;
        }

        $this->showRecommendations = true;
        $this->selectedDay = 'Senin'; // Default to first day

        $totalSlots = array_sum(array_map('count', $this->recommendations));

        if ($totalSlots === 0) {
            Notification::make()
                ->title('Tidak ada slot tersedia')
                ->body("Lab dengan kapasitas >= {$jumlahSiswa} PC dan slot sesi {$sesi} tidak ditemukan.")
                ->warning()
                ->send();
        } else {
            Notification::make()
                ->title('Ditemukan ' . $totalSlots . ' pilihan jadwal')
                ->body('Klik salah satu kartu untuk membuat jadwal.')
                ->success()
                ->send();
        }
    }

    /**
     * Create schedule from selected recommendation
     */
    public function createSchedule(int $labId, int $slotId): void
    {
        $courseId = $this->data['course_id'] ?? null;
        $lecturerId = $this->data['lecturer_id'] ?? null;
        $kelompokCode = $this->data['kelompok_code'] ?? null;

        $course = Course::with('prodi')->find($courseId);
        $lab = Laboratorium::find($labId);
        $slot = TimeSlot::find($slotId);

        if (!$course || !$lab || !$slot || !$this->selectedDay) {
            Notification::make()
                ->title('Data tidak lengkap')
                ->danger()
                ->send();
            return;
        }

        // Generate kelompok dari prodi code + kelompok_code
        $kelompok = null;
        if ($kelompokCode) {
            if ($course->prodi && $course->prodi->code) {
                $kelompok = $course->prodi->code . '.' . $kelompokCode;
            } else {
                $kelompok = $kelompokCode;
            }
        }

        $service = app(SchedulingService::class);

        // Double-check for conflicts
        if ($service->hasConflict($labId, $this->selectedDay, $slotId, $course->sks)) {
            Notification::make()
                ->title('Slot sudah terisi!')
                ->body('Jadwal bentrok dengan yang sudah ada. Silakan pilih slot lain.')
                ->danger()
                ->send();

            // Refresh recommendations
            $this->findAvailableSlots();
            return;
        }

        // Calculate end time
        $endTime = $service->calculateEndTime($slot, $course->sks);

        // Create schedule
        $schedule = Schedule::create([
            'course_id' => $courseId,
            'lecturer_id' => $lecturerId,
            'laboratorium_id' => $labId,
            'day' => $this->selectedDay,
            'time_slot_id' => $slotId,
            'duration_slots' => $course->sks,
            'start_time' => Carbon::parse($slot->start_time)->format('H:i:s'),
            'end_time' => $endTime . ':00',
            'kelompok' => $kelompok,
            'jumlah_siswa' => $this->data['jumlah_siswa'] ?? null,
            'sesi' => $this->data['sesi'] ?? null,
        ]);

        Notification::make()
            ->title('Jadwal berhasil dibuat!')
            ->body("{$course->name} - {$lab->ruang} - {$this->selectedDay} " .
                Carbon::parse($slot->start_time)->format('H:i') . " - {$endTime}")
            ->success()
            ->send();

        // Reset form
        $this->data = [];
        $this->resetRecommendations();
        $this->form->fill();
    }

    /**
     * Select day tab
     */
    public function selectDay(string $day): void
    {
        $this->selectedDay = $day;
    }

    /**
     * Get the course details for display
     */
    public function getCourseProperty(): ?Course
    {
        $courseId = $this->data['course_id'] ?? null;
        return $courseId ? Course::with('prodi')->find($courseId) : null;
    }

    /**
     * Toggle import modal
     */
    public function toggleImportModal(): void
    {
        $this->showImportModal = !$this->showImportModal;
        $this->showImportPreview = false;
        $this->importResults = [];
        $this->importSummary = [];
        $this->unplottedSchedules = [];
        $this->importFile = null;
    }

    /**
     * Process uploaded Excel and generate preview
     */
    public function processImport(): void
    {
        if (!$this->importFile) {
            Notification::make()
                ->title('Error')
                ->body('Pilih file Excel terlebih dahulu')
                ->danger()
                ->send();
            return;
        }

        try {
            $import = new BulkScheduleImport();

            // Get the file path
            $filePath = $this->importFile->getRealPath();

            Excel::import($import, $filePath);

            $this->importResults = $import->getResults();
            $this->importSummary = $import->getSummary();
            $this->unplottedSchedules = $import->getUnplottedSchedules();
            $this->showImportPreview = true;

            Notification::make()
                ->title('Preview Siap')
                ->body("Total {$this->importSummary['total']} jadwal diproses")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Gagal memproses file: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Confirm and save import results
     */
    public function confirmImport(): void
    {
        if (empty($this->importResults)) {
            Notification::make()
                ->title('Error')
                ->body('Tidak ada data untuk diimport')
                ->danger()
                ->send();
            return;
        }

        // REPLACE mode: Delete existing schedules first
        Schedule::truncate();

        $imported = 0;
        $skipped = 0;

        foreach ($this->importResults as $result) {
            // Skip error rows (no available slot)
            if ($result['status'] === 'error') {
                $skipped++;
                continue;
            }

            // Determine sesi based on time
            $sesi = 'pagi';
            if ($result['start_time'] && $result['start_time'] >= '18:30') {
                $sesi = 'malam';
            } elseif ($result['start_time'] && $result['start_time'] >= '12:30') {
                $sesi = 'siang';
            }

            Schedule::create([
                'course_id' => $result['course_id'],
                'lecturer_id' => null, // Dosen kosong
                'laboratorium_id' => $result['laboratorium_id'],
                'time_slot_id' => $result['time_slot_id'],
                'kelompok' => $result['kelompok'],
                'jumlah_siswa' => null,
                'sesi' => $sesi,
                'day' => $result['day'],
                'start_time' => $result['start_time'],
                'end_time' => $result['end_time'],
                'duration_slots' => 2, // Default 2 slot untuk 2 SKS
            ]);

            $imported++;
        }

        Notification::make()
            ->title('Import Berhasil')
            ->body("Berhasil import {$imported} jadwal, {$skipped} dilewati")
            ->success()
            ->send();

        // Reset state
        $this->showImportModal = false;
        $this->showImportPreview = false;
        $this->importResults = [];
        $this->importSummary = [];
        $this->unplottedSchedules = [];
        $this->importFile = null;
    }

    /**
     * Cancel import and reset state
     */
    public function cancelImport(): void
    {
        $this->showImportModal = false;
        $this->showImportPreview = false;
        $this->importResults = [];
        $this->importSummary = [];
        $this->unplottedSchedules = [];
        $this->importFile = null;
        $this->previewPage = 1;
    }

    /**
     * Go to next page in preview
     */
    public function nextPage(): void
    {
        $totalPages = ceil(count($this->importResults) / $this->perPage);
        if ($this->previewPage < $totalPages) {
            $this->previewPage++;
        }
    }

    /**
     * Go to previous page in preview
     */
    public function prevPage(): void
    {
        if ($this->previewPage > 1) {
            $this->previewPage--;
        }
    }

    /**
     * Go to specific page
     */
    public function goToPage(int $page): void
    {
        $totalPages = ceil(count($this->importResults) / $this->perPage);
        if ($page >= 1 && $page <= $totalPages) {
            $this->previewPage = $page;
        }
    }

    /**
     * Get paginated results for preview
     */
    public function getPaginatedResults(): array
    {
        $offset = ($this->previewPage - 1) * $this->perPage;
        return array_slice($this->importResults, $offset, $this->perPage);
    }

    /**
     * Get total pages
     */
    public function getTotalPages(): int
    {
        return (int) ceil(count($this->importResults) / $this->perPage);
    }
}

