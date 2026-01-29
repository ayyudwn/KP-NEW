<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SoftwareInventoryResource\Pages;
use App\Models\Inventory;
use App\Models\SoftwareDetail;
use Filament\Forms\Components\DatePicker;
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

class SoftwareInventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static ?string $modelLabel = 'Inventaris Software';
    protected static ?string $pluralModelLabel = 'Inventaris Software';
    protected static ?string $navigationIcon = 'heroicon-o-code-bracket-square';

    protected static bool $shouldRegisterNavigation = false;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('inventoriable_type', SoftwareDetail::class);

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
                Section::make('Informasi Umum Software')
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
                                        ->where('inventoriable_type', 'App\Models\SoftwareDetail')
                                        ->whereNotNull('kode_inventaris')
                                        ->orderByRaw("CAST(SUBSTRING_INDEX(kode_inventaris, '/', -1) AS UNSIGNED) DESC")
                                        ->first();

                                    $lastNumber = 0;
                                    if ($lastInventory && $lastInventory->kode_inventaris) {
                                        // Extract the last number from kode like "UDN/LABKOM/INV/SOFTWARE/D2A/12"
                                        $parts = explode('/', $lastInventory->kode_inventaris);
                                        $lastNumber = (int) end($parts);
                                    }

                                    $nomorUrut = str_pad($lastNumber + 1, 2, '0', STR_PAD_LEFT);

                                    // Set nomor inventaris yang akan di-generate
                                    $set('preview_kode_inventaris', "UDN/LABKOM/INV/SOFTWARE/{$namaLab}/{$nomorUrut}");
                                } else {
                                    $set('preview_kode_inventaris', null);
                                }
                            }),

                        Select::make('software_detail_id')
                            ->label('Nama Software')
                            ->options(function () {
                                return SoftwareDetail::whereNotNull('code')
                                    ->orderBy('nama')
                                    ->get()
                                    ->mapWithKeys(fn($sw) => [$sw->id => "[{$sw->code}] {$sw->nama}"]);
                            })
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state) {
                                    $software = SoftwareDetail::find($state);
                                    if ($software) {
                                        $set('nama_barang', $software->nama);
                                    }
                                }
                            })
                            ->helperText('Pilih software dari daftar master')
                            ->createOptionForm([
                                TextInput::make('code')
                                    ->label('Kode Software')
                                    ->required()
                                    ->unique('software_details', 'code')
                                    ->maxLength(50)
                                    ->placeholder('PREMIERE'),
                                TextInput::make('nama')
                                    ->label('Nama Software')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Adobe Premiere Pro'),
                            ])
                            ->createOptionUsing(function (array $data) {
                                return SoftwareDetail::create($data)->id;
                            }),

                        TextInput::make('nama_barang')
                            ->label('Nama Software (Otomatis)')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Terisi otomatis dari software yang dipilih'),
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

                Section::make('Detail Lainnya')
                    ->schema([
                        TextInput::make('details.jenis_lisensi')->label('Versi'),
                        TextInput::make('details.nomor_lisensi')->label('Nomor Lisensi / Kunci Produk'),
                        DatePicker::make('details.tanggal_kadaluarsa')->label('Tanggal Kadaluarsa (jika ada)'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_inventaris')->searchable()->sortable(),
                TextColumn::make('nama_barang')->label('Nama Software')->searchable(),
                TextColumn::make('inventoriable.jenis_lisensi')->label('Versi')->toggleable(),
                TextColumn::make('laboratorium.ruang')->sortable()->badge(),
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                // Note: Tidak menghapus inventoriable karena SoftwareDetail adalah master data
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Note: Tidak menghapus inventoriable karena SoftwareDetail adalah master data
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListSoftwareInventories::route('/'),
            'create' => Pages\CreateSoftwareInventory::route('/create'),
            'edit' => Pages\EditSoftwareInventory::route('/{record}/edit'),
        ];
    }
}
