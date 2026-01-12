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
                    ->description('Isi data mata kuliah, dosen, dan kelompok, lalu klik "Cari Slot Tersedia"')
                    ->schema([
                        Select::make('course_id')
                            ->label('Mata Kuliah')
                            ->options(function () {
                                return Course::with('prodi')
                                    ->get()
                                    ->mapWithKeys(function ($course) {
                                        $label = $course->name;
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
                            ->afterStateUpdated(fn() => $this->resetRecommendations()),

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

                        TextInput::make('kelompok')
                            ->label('Kelompok/Kelas')
                            ->placeholder('A, B, C, atau kosongkan')
                            ->maxLength(50),
                    ])
                    ->columns(3),
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

        if (!$courseId) {
            Notification::make()
                ->title('Pilih mata kuliah terlebih dahulu')
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

        $service = app(SchedulingService::class);
        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

        $this->recommendations = [];

        foreach ($days as $day) {
            $dayRecommendations = [];

            $availableLabs = $service->getAvailableLabs($course);

            foreach ($availableLabs as $lab) {
                $availableSlots = $service->getAvailableSlots($lab, $day, $course->sks);

                if ($availableSlots->isNotEmpty()) {
                    $isPriority = $course->prodi_id
                        ? $lab->priorityProdis->contains('id', $course->prodi_id)
                        : false;

                    foreach ($availableSlots as $slot) {
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
                ->body('Semua lab penuh atau tidak memenuhi syarat software/kapasitas.')
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
        $kelompok = $this->data['kelompok'] ?? null;

        $course = Course::find($courseId);
        $lab = Laboratorium::find($labId);
        $slot = TimeSlot::find($slotId);

        if (!$course || !$lab || !$slot || !$this->selectedDay) {
            Notification::make()
                ->title('Data tidak lengkap')
                ->danger()
                ->send();
            return;
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
