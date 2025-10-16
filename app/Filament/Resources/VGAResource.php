<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\AllHardware;
use App\Filament\Clusters\Hardware;
use App\Filament\Resources\VGAResource\Pages;
use App\Filament\Resources\VGAResource\RelationManagers;
use App\Models\VGA;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VGAResource extends Resource
{
    protected static ?string $model = VGA::class;

    protected static ?string $navigationIcon = 'clarity-box-plot-line';

    protected static ?string $slug = 'vga';

    protected static ?string $navigationLabel = 'VGA';

    protected static ?string $modelLabel = 'VGA';

    protected static ?string $navigationGroup = 'Data Hardware';

    // protected static ?string $cluster = AllHardware::class;

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('no_inventaris')
                    ->label('No Inventaris')
                    ->disabled() // Dibuat otomatis di model
                    ->dehydrated(false), // Tidak dikirim ke backend, karena sudah diisi otomatis

                TextInput::make('merk')
                    ->label('Merk')
                    ->required()
                    ->maxLength(255),

                TextInput::make('tipe')
                    ->label('Tipe VGA')
                    ->required()
                    ->maxLength(255),

                TextInput::make('kapasitas')
                    ->label('Kapasitas VRAM (GB)')
                    ->numeric()
                    ->minValue(1)
                    ->required(),

                Textarea::make('spesifikasi')
                    ->label('Spesifikasi')  
                    ->rows(4)
                    ->maxLength(500),

                Select::make('tahun')
                    ->label('Tahun')
                    ->options(function () {
                        $tahunSekarang = date('Y');
                        return array_combine(
                            range($tahunSekarang, $tahunSekarang - 20),
                            range($tahunSekarang, $tahunSekarang - 20)
                        );
                    })
                    ->required(),
                Select::make('bulan')
                    ->label('Bulan Pengadaan')
                    ->options([
                        1 => 'Januari',
                        2 => 'Februari',
                        3 => 'Maret',
                        4 => 'April',
                        5 => 'Mei',
                        6 => 'Juni',
                        7 => 'Juli',
                        8 => 'Agustus',
                        9 => 'September',
                        10 => 'Oktober',
                        11 => 'November',
                        12 => 'Desember',
                    ]),
                TextInput::make('stok')
                    ->label('Stok')
                    ->required()
                    ->minValue(0)
                    ->default(0)
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_inventaris')
                    ->label('No Inventaris')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('merk')
                    ->label('Merk')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('tipe')
                    ->label('Tipe VGA')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('kapasitas')
                    ->label('Kapasitas VRAM')
                    ->numeric()
                    ->sortable()
                    ->suffix(' GB')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('spesifikasi')
                    ->label('Spesifikasi')
                    ->limit(40)
                    ->tooltip('Klik untuk melihat spesifikasi lengkap')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('bulan')
                    ->label('Bulan Pengadaan')
                    ->formatStateUsing(function (?string $state): ?string {
                        if (empty($state)) {
                            return null;
                        }
                        $months = [
                            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                        ];
                        return $months[(int)$state] ?? $state;
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('tahun')
                    ->label('Tahun')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('stok')
                    ->label('Stok')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListVGAS::route('/'),
            'create' => Pages\CreateVGA::route('/create'),
            'edit' => Pages\EditVGA::route('/{record}/edit'),
        ];
    }
}
