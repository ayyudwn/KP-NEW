<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LaboratoriumResource\Pages;
use App\Filament\Resources\LaboratoriumResource\RelationManagers;
use App\Models\Laboratorium;
use Filament\Forms;
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
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LaboratoriumResource extends Resource
{
    protected static ?string $model = Laboratorium::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $slug = 'laboratorium';

    protected static ?string $navigationLabel = 'Data Laboratorium';

    protected static ?string $navigationGroup = 'MASTER DATA';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Dasar')
                    ->schema([
                        Select::make('kategori_id')
                            ->label('Kategori')
                            ->relationship('kategori', 'nama_kategori')
                            ->required()
                            ->preload()
                            ->searchable()
                            ->placeholder('Select kategori'),

                        TextInput::make('ruang')
                            ->label('Ruang Laboratorium')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('kapasitas')
                            ->label('Kapasitas Ruangan')
                            ->numeric()
                            ->minValue(1)
                            ->required(),

                        TextInput::make('pc_siap')
                            ->label('PC Siap Pakai')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->helperText('Jumlah PC yang siap untuk praktikum'),

                        TextInput::make('pc_backup')
                            ->label('PC Backup')
                            ->numeric()
                            ->minValue(0)
                            ->required(),

                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Pengaturan Penjadwalan')
                    ->description('Pengaturan untuk sistem penjadwalan otomatis')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif untuk Penjadwalan')
                            ->default(true)
                            ->helperText('Nonaktifkan jika lab sedang dalam perbaikan'),

                        Forms\Components\TimePicker::make('operating_start')
                            ->label('Jam Operasional Mulai')
                            ->default('07:00')
                            ->required(),

                        Forms\Components\TimePicker::make('operating_end')
                            ->label('Jam Operasional Selesai')
                            ->default('21:00')
                            ->required(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Software & Prioritas')
                    ->description('Pengaturan software dan prioritas program studi')
                    ->schema([
                        Forms\Components\Select::make('software')
                            ->label('Software Terinstal')
                            ->relationship('software', 'nama')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Pilih software yang terinstal di lab ini'),

                        Forms\Components\Select::make('priorityProdis')
                            ->label('Prioritas Program Studi')
                            ->relationship('priorityProdis', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Program studi yang diprioritaskan untuk lab ini'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ruang')
                    ->label('Ruang Laboratorium')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('kategori.nama_kategori')
                    ->label('Kategori Laboratorium')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('kapasitas')
                    ->label('Kapasitas')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('kategori_id')
                    ->label('Filter Kategori')
                    ->relationship('kategori', 'nama_kategori'),
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
            'index' => Pages\ListLaboratoria::route('/'),
            // 'create' => Pages\CreateLaboratorium::route('/create'),
            // 'edit' => Pages\EditLaboratorium::route('/{record}/edit'),
        ];
    }
}
