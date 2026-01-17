<?php

namespace App\Filament\Pages;

use App\Models\Course;
use App\Models\Laboratorium;
use App\Models\Lecturer;
use App\Models\Schedule;
use App\Models\TimeSlot;
use App\Services\SchedulingService;
use Carbon\Carbon;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;

class ScheduleWizard extends Page implements HasForms
{
    use InteractsWithForms;

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
                        Select::make('course_id')
                            ->label('Mata Kuliah')
                            ->options(function () {
                                return Course::with('prodi')
                                    ->get()
                                    ->mapWithKeys(function ($course) {
                                        $label = $course->name;
                                        if ($course->code) {
                                            $label = "[{$course->code}] " . $label;
                                        }
                                        if ($course->prodi) {
                                            $label .= " ({$course->prodi->name})";
                                        }
                                        $label .= " - {$course->sks} SKS";
                                        return [$course->id => $label];
                                    });
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
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
}
