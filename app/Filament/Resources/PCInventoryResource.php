<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PCInventoryResource\Pages;
use App\Models\Inventory;
use App\Models\PCDetail;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Actions\Action;
use App\Models\Laboratorium;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid as InfoGrid;
use Filament\Infolists\Infolist;

class PCInventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static ?string $modelLabel = 'Inventaris PC';
    protected static ?string $pluralModelLabel = 'Inventaris PC';
    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    // Sembunyikan dari navigasi utama karena sudah dibuat dinamis
    protected static bool $shouldRegisterNavigation = false;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('inventoriable_type', PCDetail::class);

        // Filter by user's authorized labs
        $user = auth()->user();
        if ($user && !$user->hasRole('super_admin')) {
            $authorizedLabIds = $user->getAuthorizedLabIds('view');
            $query->whereIn('laboratorium_id', $authorizedLabIds);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Umum PC')
                    ->schema([
                        Select::make('laboratorium_id')
                            ->label('Laboratorium')
                            ->relationship(
                                'laboratorium',
                                'ruang',
                                fn(Builder $query) => auth()->user()->hasRole('super_admin')
                                ? $query
                                : $query->whereIn('id', auth()->user()->getAuthorizedLabIds('view'))
                            )
                            ->required()
                            ->preload()
                            ->searchable()
                            ->live()
                            ->default(function () {
                                // Auto-fill berdasarkan URL parameter jika ada
                                $labId = request()->input('tableFilters.laboratorium.value')
                                    ?? request()->input('tableFilters')['laboratorium']['value'] ?? null;

                                if ($labId) {
                                    return (int) $labId;
                                }
                                return null;
                            })
                            ->afterStateHydrated(function ($component, $state) {
                                // Hook ini dipanggil setelah form dimuat
                                if (!$state) {
                                    $labId = request()->input('tableFilters.laboratorium.value')
                                        ?? request()->input('tableFilters')['laboratorium']['value'] ?? null;

                                    if ($labId) {
                                        $component->state((int) $labId);
                                    }
                                }
                            })
                            ->hidden(function () {
                                // Sembunyikan field jika ada parameter lab di URL
                                $labId = request()->input('tableFilters.laboratorium.value')
                                    ?? request()->input('tableFilters')['laboratorium']['value'] ?? null;
                                return (bool) $labId;
                            })
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state) {
                                    // Ambil nama laboratorium
                                    $laboratorium = \App\Models\Laboratorium::find($state);
                                    $namaLab = $laboratorium ? strtoupper($laboratorium->ruang) : 'LAB';

                                    // Cari nomor urut tertinggi yang pernah digunakan untuk lab ini
                                    $lastInventory = \App\Models\Inventory::where('laboratorium_id', $state)
                                        ->where('inventoriable_type', 'App\Models\PCDetail')
                                        ->whereNotNull('kode_inventaris')
                                        ->orderByRaw("CAST(SUBSTRING_INDEX(kode_inventaris, '/', -1) AS UNSIGNED) DESC")
                                        ->first();

                                    $lastNumber = 0;
                                    if ($lastInventory && $lastInventory->kode_inventaris) {
                                        $parts = explode('/', $lastInventory->kode_inventaris);
                                        $lastNumber = (int) end($parts);
                                    }

                                    $nomorUrut = str_pad($lastNumber + 1, 2, '0', STR_PAD_LEFT);

                                    // Set nomor inventaris yang akan di-generate
                                    $set('preview_kode_inventaris', "UDN/LABKOM/INV/PC/{$namaLab}/{$nomorUrut}");
                                } else {
                                    $set('preview_kode_inventaris', null);
                                }
                            }),
                        TextInput::make('preview_kode_inventaris')
                            ->label('No Inventaris (Preview)')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Pilih laboratorium terlebih dahulu')
                            ->helperText('Nomor inventaris yang akan di-generate otomatis')
                            ->extraAttributes(['style' => 'background-color: #f3f4f6; font-weight: 500;']),
                        DatePicker::make('tanggal_pengadaan'),
                        Select::make('kondisi')
                            ->options(['Baik' => 'Baik', 'Rusak Ringan' => 'Rusak Ringan', 'Rusak Berat' => 'Rusak Berat', 'Dalam Perbaikan' => 'Dalam Perbaikan'])
                            ->required()
                            ->default('Baik'),
                    ])->columns(2)
                    ->extraAttributes(function () {
                        // Auto-trigger afterStateUpdated untuk preview kode inventaris
                        $labId = request()->input('tableFilters.laboratorium.value')
                            ?? request()->input('tableFilters')['laboratorium']['value'] ?? null;

                        if ($labId) {
                            return [
                                'x-data' => '{
                                    init() {
                                        this.$nextTick(() => {
                                            const labSelect = this.$el.querySelector(\'[wire\\:model*="laboratorium_id"]\');
                                            if (labSelect && labSelect.value) {
                                                labSelect.dispatchEvent(new Event(\'change\', { bubbles: true }));
                                            }
                                        });
                                    }
                                }'
                            ];
                        }
                        return [];
                    }),

                Section::make('Spesifikasi Komponen PC')
                    ->description('Pilih komponen dari master data yang tersedia.')
                    ->schema([
                        // Data di dalam section ini akan disimpan ke tabel pc_details
                        // melalui logika di halaman Create/Edit
                        Grid::make(3)->schema([
                            Select::make('details.processor_id')
                                ->label('Processor')
                                ->options(function () {
                                    return \App\Models\Processor::all()->pluck('full_name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->placeholder('Pilih Processor'),
                            Select::make('details.motherboard_id')
                                ->label('Motherboard')
                                ->options(function () {
                                    return \App\Models\Motherboard::all()->pluck('full_name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->placeholder('Pilih Motherboard'),
                            Select::make('details.ram_id')
                                ->label('RAM')
                                ->options(function () {
                                    return \App\Models\RAM::all()->pluck('full_name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->placeholder('Pilih RAM'),
                            Select::make('details.penyimpanan_id')
                                ->label('Penyimpanan')
                                ->options(function () {
                                    return \App\Models\Penyimpanan::all()->pluck('full_name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->placeholder('Pilih Penyimpanan'),
                            Select::make('details.vga_id')
                                ->label('VGA')
                                ->options(function () {
                                    return \App\Models\VGA::all()->pluck('full_name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->placeholder('Pilih VGA'),
                            Select::make('details.psu_id')
                                ->label('PSU')
                                ->options(function () {
                                    return \App\Models\PSU::all()->pluck('full_name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->placeholder('Pilih PSU'),
                            Select::make('details.keyboard_id')
                                ->label('Keyboard')
                                ->options(function () {
                                    return \App\Models\Keyboard::all()->pluck('full_name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->placeholder('Pilih Keyboard'),
                            Select::make('details.mouse_id')
                                ->label('Mouse')
                                ->options(function () {
                                    return \App\Models\Mouse::all()->pluck('full_name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->placeholder('Pilih Mouse'),
                            Select::make('details.monitor_id')
                                ->label('Monitor')
                                ->options(function () {
                                    return \App\Models\Monitor::all()->pluck('full_name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->placeholder('Pilih Monitor'),
                            Select::make('details.dvd_id')
                                ->label('DVD (Optional)')
                                ->options(function () {
                                    return \App\Models\DVD::all()->pluck('full_name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->placeholder('Pilih DVD (Opsional)')
                                ->helperText('Pilih DVD jika PC memiliki drive DVD'),
                            Select::make('details.headphone_id')
                                ->label('Headphone (Optional)')
                                ->options(function () {
                                    return \App\Models\Headphone::all()->pluck('full_name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->placeholder('Pilih Headphone (Opsional)')
                                ->helperText('Pilih headphone jika diperlukan'),
                        ])
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_inventaris')
                    ->label('No Inventaris')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('laboratorium.ruang')->sortable()->badge(),
                TextColumn::make('kondisi')->sortable()->badge()->color(fn(string $state): string => match ($state) {
                    'Baik' => 'success',
                    'Rusak Ringan' => 'warning',
                    'Rusak Berat' => 'danger',
                    'Dalam Perbaikan' => 'info',
                }),
                TextColumn::make('inventoriable.processor.tipe')->label('CPU')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('inventoriable.ram.tipe')->label('RAM')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('inventoriable.motherboard.tipe')->label('Motherboard')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('inventoriable.penyimpanan.tipe')->label('Storage')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('inventoriable.vga.tipe')->label('VGA')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('inventoriable.psu.tipe')->label('PSU')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('inventoriable.keyboard.tipe')->label('Keyboard')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('inventoriable.mouse.tipe')->label('Mouse')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('inventoriable.monitor.tipe')->label('Monitor')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('inventoriable.dvd.tipe')->label('DVD')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('inventoriable.headphone.tipe')->label('Headphone')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('laboratorium')
                    ->relationship(
                        'laboratorium',
                        'ruang',
                        fn(Builder $query) => auth()->user()->hasRole('super_admin')
                        ? $query
                        : $query->whereIn('id', auth()->user()->getAuthorizedLabIds('view'))
                    )
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Lihat Inventaris PC')
                    ->infolist(fn(Infolist $infolist): Infolist => static::infolist($infolist)),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Inventory $record) {
                        // Hapus record detail terkait sebelum menghapus record inventaris utama
                        $record->inventoriable?->delete();
                    }),
                Tables\Actions\ReplicateAction::make()
                    ->label('Duplikat')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('success')
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->before(function ($records) {
                        $records->each(fn(Inventory $record) => $record->inventoriable?->delete());
                    }),
                ]),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    // Arahkan action untuk memanggil metode 'exportToExcel' di Livewire Component (List Page)
                    ->action(fn($livewire) => $livewire->exportToExcel())
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfoSection::make('Informasi Umum PC')
                    ->schema([
                        TextEntry::make('laboratorium.ruang')
                            ->label('Laboratorium')
                            ->badge(),
                        TextEntry::make('kode_inventaris')
                            ->label('No Inventaris'),
                        TextEntry::make('tanggal_pengadaan')
                            ->label('Tanggal Pengadaan')
                            ->date('d M Y')
                            ->placeholder('-'),
                        TextEntry::make('kondisi')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'Baik' => 'success',
                                'Rusak Ringan' => 'warning',
                                'Rusak Berat' => 'danger',
                                'Dalam Perbaikan' => 'info',
                                default => 'gray',
                            }),
                    ])->columns(2),

                InfoSection::make('Spesifikasi Komponen PC')
                    ->description('Detail komponen hardware yang terpasang.')
                    ->schema([
                        InfoGrid::make(3)->schema([
                            TextEntry::make('inventoriable.processor.full_name')
                                ->label('Processor')
                                ->placeholder('-'),
                            TextEntry::make('inventoriable.motherboard.full_name')
                                ->label('Motherboard')
                                ->placeholder('-'),
                            TextEntry::make('inventoriable.ram.full_name')
                                ->label('RAM')
                                ->placeholder('-'),
                            TextEntry::make('inventoriable.penyimpanan.full_name')
                                ->label('Penyimpanan')
                                ->placeholder('-'),
                            TextEntry::make('inventoriable.vga.full_name')
                                ->label('VGA')
                                ->placeholder('-'),
                            TextEntry::make('inventoriable.psu.full_name')
                                ->label('PSU')
                                ->placeholder('-'),
                            TextEntry::make('inventoriable.keyboard.full_name')
                                ->label('Keyboard')
                                ->placeholder('-'),
                            TextEntry::make('inventoriable.mouse.full_name')
                                ->label('Mouse')
                                ->placeholder('-'),
                            TextEntry::make('inventoriable.monitor.full_name')
                                ->label('Monitor')
                                ->placeholder('-'),
                            TextEntry::make('inventoriable.dvd.full_name')
                                ->label('DVD')
                                ->placeholder('Tidak ada'),
                            TextEntry::make('inventoriable.headphone.full_name')
                                ->label('Headphone')
                                ->placeholder('Tidak ada'),
                        ]),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPCInventories::route('/'),
            'create' => Pages\CreatePCInventory::route('/create'),
            'edit' => Pages\EditPCInventory::route('/{record}/edit'),
        ];
    }
}
