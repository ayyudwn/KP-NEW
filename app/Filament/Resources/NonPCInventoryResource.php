<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NonPCInventoryResource\Pages;
use App\Models\Inventory;
use App\Models\NonPCDetail;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NonPCInventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static ?string $modelLabel = 'Inventaris Non-PC';
    protected static ?string $pluralModelLabel = 'Inventaris Non-PC';
    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static bool $shouldRegisterNavigation = false;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('inventoriable_type', NonPCDetail::class);

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
                Section::make('Informasi Umum Non-PC')
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
                                        ->where('inventoriable_type', 'App\Models\NonPCDetail')
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
                                    $set('preview_kode_inventaris', "UDN/LABKOM/INV/NPC/{$namaLab}/{$nomorUrut}");
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
                        TextInput::make('nama_barang')
                            ->label('Nama Barang')
                            ->required(),
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

                Section::make('Detail Barang')
                    ->schema([
                        TextInput::make('details.merk')->label('Jumlah'),
                        TextInput::make('details.model')->label('Model/Tipe'),
                        Textarea::make('details.spesifikasi')
                            ->label('Spesifikasi Tambahan')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_inventaris')->searchable()->sortable(),
                TextColumn::make('nama_barang')->searchable(),
                TextColumn::make('inventoriable.merk')->label('Jumlah')->toggleable(),
                TextColumn::make('laboratorium.ruang')->sortable()->badge(),
                TextColumn::make('kondisi')->sortable()->badge()->color(fn(string $state): string => match ($state) {
                    'Baik' => 'success',
                    'Rusak Ringan' => 'warning',
                    'Rusak Berat' => 'danger',
                    'Dalam Perbaikan' => 'info',
                }),
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
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Inventory $record) {
                        $record->inventoriable?->delete();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->before(function ($records) {
                        $records->each(fn(Inventory $record) => $record->inventoriable?->delete());
                    }),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Export Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(fn($livewire) => $livewire->exportToExcel())
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNonPCInventories::route('/'),
            'create' => Pages\CreateNonPCInventory::route('/create'),
            'edit' => Pages\EditNonPCInventory::route('/{record}/edit'),
        ];
    }
}
