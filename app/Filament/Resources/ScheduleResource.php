<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScheduleResource\Pages;
use App\Models\Course;
use App\Models\Laboratorium;
use App\Models\Lecturer;
use App\Models\Schedule;
use App\Models\TimeSlot;
use App\Services\SchedulingService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ScheduleResource extends Resource
{
    protected static ?string $model = Schedule::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Penjadwalan';

    protected static ?string $modelLabel = 'Jadwal Kuliah';

    protected static ?string $pluralModelLabel = 'Jadwal Kuliah';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Mata Kuliah')
                    ->description('Pilih mata kuliah untuk dijadwalkan')
                    ->schema([
                        Forms\Components\Select::make('prodi_filter')
                            ->label('Program Studi')
                            ->options(\App\Models\Prodi::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(function (Set $set, $old, $state, ?Schedule $record) {
                                // Only reset if user actually changed the value (not during hydration)
                                // And only if not editing an existing record with data
                                if ($old !== null && $old !== $state) {
                                    $set('course_id', null);
                                    $set('laboratorium_id', null);
                                    $set('time_slot_id', null);
                                    $set('kelompok_code', null);
                                    $set('kelompok', null);
                                }
                            })
                            ->afterStateHydrated(function ($component, $state, ?Schedule $record) {
                                if (!$state && $record?->course?->prodi_id) {
                                    $component->state($record->course->prodi_id);
                                }
                            })
                            ->helperText('Pilih program studi terlebih dahulu'),

                        Forms\Components\Select::make('course_id')
                            ->label('Mata Kuliah')
                            ->options(function (Get $get) {
                                $prodiId = $get('prodi_filter');
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
                            ->disabled(fn(Get $get) => !$get('prodi_filter'))
                            ->afterStateUpdated(function (Set $set, $old, $state) {
                                // Only reset if user actually changed the value
                                if ($old !== null && $old !== $state) {
                                    $set('laboratorium_id', null);
                                    $set('time_slot_id', null);
                                    $set('kelompok_code', null);
                                    $set('kelompok', null);
                                }
                            })
                            ->helperText(fn(Get $get) => !$get('prodi_filter') ? 'Pilih prodi terlebih dahulu' : 'Pilih mata kuliah'),

                        Forms\Components\Select::make('lecturer_id')
                            ->label('Dosen Pengampu')
                            ->relationship('lecturer', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Dosen')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->helperText('Opsional: dapat ditentukan nanti'),

                        Forms\Components\TextInput::make('kelompok_code')
                            ->label('Kode Kelompok/Kelas')
                            ->placeholder('0001')
                            ->maxLength(20)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
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
                            ->helperText(function (Get $get) {
                                $courseId = $get('course_id');
                                if ($courseId) {
                                    $course = Course::with('prodi')->find($courseId);
                                    if ($course && $course->prodi && $course->prodi->code) {
                                        return "Kode prodi: {$course->prodi->code}";
                                    }
                                }
                                return 'Input kode kelompok (misal: 0001)';
                            }),

                        Forms\Components\TextInput::make('kelompok')
                            ->label('Kelompok (Otomatis)')
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('A11.0001')
                            ->helperText('KodeProdi.KodeKelompok'),

                        Forms\Components\TextInput::make('jumlah_siswa')
                            ->label('Jumlah Siswa')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->placeholder('30'),

                        Forms\Components\Select::make('sesi')
                            ->label('Sesi Waktu')
                            ->options([
                                'pagi' => '🌅 Pagi (07:00)',
                                'siang' => '☀️ Siang (12:30)',
                                'malam' => '🌙 Malam (18:30)',
                            ])
                            ->required(),
                    ])
                    ->columns(6),

                Forms\Components\Section::make('Pemilihan Laboratorium')
                    ->description('Pilih laboratorium untuk jadwal (bebas pilih lab mana saja)')
                    ->schema([
                        Forms\Components\Select::make('laboratorium_id')
                            ->label('Laboratorium')
                            ->options(function () {
                                return Laboratorium::where('is_active', true)
                                    ->orderBy('ruang')
                                    ->pluck('ruang', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('time_slot_id', null);
                            })
                            ->disabled(fn(Get $get) => !$get('course_id'))
                            ->placeholder(fn(Get $get) => $get('course_id')
                                ? 'Pilih laboratorium'
                                : 'Pilih mata kuliah terlebih dahulu')
                            ->helperText('Semua lab aktif tersedia. Pastikan waktu tidak bertabrakan.'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Jadwal Waktu')
                    ->description('Pilih hari dan slot waktu yang tersedia')
                    ->schema([
                        Forms\Components\Select::make('day')
                            ->label('Hari')
                            ->options([
                                'Senin' => 'Senin',
                                'Selasa' => 'Selasa',
                                'Rabu' => 'Rabu',
                                'Kamis' => 'Kamis',
                                'Jumat' => 'Jumat',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, $old, $state) {
                                // Only reset if user actually changed the value
                                if ($old !== null && $old !== $state) {
                                    $set('time_slot_id', null);
                                }
                            })
                            ->disabled(fn(Get $get) => !$get('laboratorium_id')),

                        Forms\Components\Select::make('time_slot_id')
                            ->label('Jam Mulai')
                            ->options(function (Get $get, ?Schedule $record) {
                                $labId = $get('laboratorium_id');
                                $day = $get('day');
                                $courseId = $get('course_id');

                                if (!$labId || !$day) {
                                    return [];
                                }

                                $lab = Laboratorium::find($labId);
                                if (!$lab) {
                                    return [];
                                }

                                $service = app(SchedulingService::class);
                                $excludeId = $record?->id;

                                // Use course SKS if available, otherwise default to 2
                                $course = $courseId ? Course::find($courseId) : null;
                                $sks = $course?->sks ?? 2;

                                $options = $service->getSlotOptionsForForm($lab, $day, $sks, $excludeId);

                                // If editing and record has time_slot_id, ensure it's in options
                                if ($record?->time_slot_id && $record?->timeSlot) {
                                    $slot = $record->timeSlot;
                                    $key = $slot->id;
                                    if (!isset($options[$key])) {
                                        $options[$key] = Carbon::parse($slot->start_time)->format('H:i') . ' - Slot #' . $slot->slot_number;
                                    }
                                }

                                return $options;
                            })
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                // Auto-update sesi based on time slot
                                if ($state) {
                                    $slot = TimeSlot::find($state);
                                    if ($slot) {
                                        $startTime = Carbon::parse($slot->start_time)->format('H:i');

                                        // Determine session based on start time
                                        if ($startTime >= '07:00' && $startTime < '12:30') {
                                            $set('sesi', 'pagi');
                                        } elseif ($startTime >= '12:30' && $startTime < '18:30') {
                                            $set('sesi', 'siang');
                                        } else {
                                            $set('sesi', 'malam');
                                        }
                                    }
                                }
                            })
                            ->rules([
                                function (Get $get, ?Schedule $record) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                        $labId = $get('laboratorium_id');
                                        $day = $get('day');
                                        $courseId = $get('course_id');

                                        if (!$labId || !$day || !$courseId || !$value) {
                                            return;
                                        }

                                        $newCourse = Course::find($courseId);
                                        $newSlot = TimeSlot::find($value);

                                        if (!$newCourse || !$newSlot) {
                                            return;
                                        }

                                        // Calculate new schedule range
                                        $newStart = $newSlot->slot_number;
                                        $newEnd = $newStart + $newCourse->sks - 1;

                                        // Fetch all existing schedules for this lab and day
                                        $existingSchedules = Schedule::where('laboratorium_id', $labId)
                                            ->where('day', $day)
                                            ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                                            ->with(['course', 'timeSlot'])
                                            ->get();

                                        foreach ($existingSchedules as $schedule) {
                                            if (!$schedule->course || !$schedule->timeSlot)
                                                continue;

                                            $existingStart = $schedule->timeSlot->slot_number;
                                            $existingEnd = $existingStart + $schedule->course->sks - 1;

                                            // Check overlap: max(start1, start2) <= min(end1, end2)
                                            if (max($existingStart, $newStart) <= min($existingEnd, $newEnd)) {
                                                $conflictTime = Carbon::parse($schedule->timeSlot->start_time)->format('H:i');
                                                $fail("Jadwal bertabrakan dengan {$schedule->course->name} ({$conflictTime})");
                                                return;
                                            }
                                        }
                                    };
                                },
                            ])
                            ->disabled(fn(Get $get) => !$get('day') || !$get('laboratorium_id'))
                            ->placeholder(function (Get $get) {
                                if (!$get('laboratorium_id')) {
                                    return 'Pilih laboratorium terlebih dahulu';
                                }
                                if (!$get('day')) {
                                    return 'Pilih hari terlebih dahulu';
                                }
                                return 'Pilih slot waktu yang tersedia';
                            })
                            ->helperText(function (Get $get, ?Schedule $record) {
                                $labId = $get('laboratorium_id');
                                $day = $get('day');
                                $courseId = $get('course_id');

                                if (!$labId || !$day || !$courseId) {
                                    return null;
                                }

                                $course = Course::find($courseId);
                                $lab = Laboratorium::find($labId);

                                if (!$course || !$lab) {
                                    return null;
                                }

                                $service = app(SchedulingService::class);
                                $filteredOptions = $service->getSlotOptionsForForm($lab, $day, $course->sks, $record?->id);

                                if (empty($filteredOptions)) {
                                    return "⚠️ Tidak ada slot {$course->sks} jam berturutan yang tersedia pada hari {$day}";
                                }

                                return "✓ " . count($filteredOptions) . " slot tersedia (durasi: {$course->sks} x 50 menit)";
                            }),

                        Forms\Components\Placeholder::make('calculated_end_time')
                            ->label('Jam Selesai')
                            ->content(function (Get $get) {
                                $slotId = $get('time_slot_id');
                                $courseId = $get('course_id');

                                if (!$slotId || !$courseId) {
                                    return '-';
                                }

                                $slot = TimeSlot::find($slotId);
                                $course = Course::find($courseId);

                                if (!$slot || !$course) {
                                    return '-';
                                }

                                $service = app(SchedulingService::class);
                                $endTime = $service->calculateEndTime($slot, $course->sks);

                                return Carbon::parse($slot->start_time)->format('H:i') . ' - ' . $endTime;
                            }),
                    ])
                    ->columns(3),

                // Hidden fields untuk backward compatibility
                Forms\Components\Hidden::make('duration_slots')
                    ->default(fn(Get $get) => Course::find($get('course_id'))?->sks ?? 1),

                Forms\Components\Hidden::make('start_time'),
                Forms\Components\Hidden::make('end_time'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('course.name')
                    ->label('Mata Kuliah')
                    ->searchable()
                    ->sortable()
                    ->description(fn(Schedule $record) => $record->course?->code ?? null),

                Tables\Columns\TextColumn::make('kelompok')
                    ->label('Kelompok')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Tidak ada')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('lecturer.name')
                    ->label('Dosen')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Belum ditentukan')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('laboratorium.ruang')
                    ->label('Laboratorium')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('day')
                    ->label('Hari')
                    ->sortable()
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Senin' => 'info',
                        'Selasa' => 'success',
                        'Rabu' => 'warning',
                        'Kamis' => 'danger',
                        'Jumat' => 'gray',
                        default => 'primary',
                    }),

                Tables\Columns\TextColumn::make('time_label')
                    ->label('Waktu')
                    ->sortable(
                        query: fn(Builder $query, string $direction) =>
                        $query->orderBy('start_time', $direction)
                    ),

                Tables\Columns\TextColumn::make('jumlah_siswa')
                    ->label('Siswa')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sesi')
                    ->label('Sesi')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn(?string $state): string => match ($state) {
                        'pagi' => '🌅 Pagi',
                        'siang' => '☀️ Siang',
                        'malam' => '🌙 Malam',
                        default => '-',
                    })
                    ->color(fn(?string $state): string => match ($state) {
                        'pagi' => 'success',
                        'siang' => 'warning',
                        'malam' => 'gray',
                        default => 'primary',
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('course.sks')
                    ->label('SKS')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('day')
            ->filters([
                Tables\Filters\SelectFilter::make('laboratorium_id')
                    ->label('Laboratorium')
                    ->relationship('laboratorium', 'ruang')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('day')
                    ->label('Hari')
                    ->options([
                        'Senin' => 'Senin',
                        'Selasa' => 'Selasa',
                        'Rabu' => 'Rabu',
                        'Kamis' => 'Kamis',
                        'Jumat' => 'Jumat',
                    ]),

                Tables\Filters\SelectFilter::make('course_id')
                    ->label('Mata Kuliah')
                    ->relationship('course', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('lecturer_id')
                    ->label('Dosen')
                    ->relationship('lecturer', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada jadwal')
            ->emptyStateDescription('Klik tombol di bawah untuk membuat jadwal baru')
            ->emptyStateIcon('heroicon-o-calendar');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchedules::route('/'),
            'create' => Pages\CreateSchedule::route('/create'),
            'edit' => Pages\EditSchedule::route('/{record}/edit'),
        ];
    }

    /**
     * Hook sebelum menyimpan untuk sync time_slot dengan start_time/end_time
     */
    public static function mutateFormDataBeforeCreate(array $data): array
    {
        return static::syncTimeSlotData($data);
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        return static::syncTimeSlotData($data);
    }

    protected static function syncTimeSlotData(array $data): array
    {
        if (!empty($data['time_slot_id']) && !empty($data['course_id'])) {
            $slot = TimeSlot::find($data['time_slot_id']);
            $course = Course::find($data['course_id']);

            if ($slot && $course) {
                $data['start_time'] = Carbon::parse($slot->start_time)->format('H:i:s');
                $data['duration_slots'] = $course->sks;

                $service = app(SchedulingService::class);
                $data['end_time'] = $service->calculateEndTime($slot, $course->sks) . ':00';
            }
        }

        return $data;
    }
}
