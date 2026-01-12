<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourseResource\Pages;
use App\Filament\Resources\CourseResource\RelationManagers;
use App\Models\Course;
use App\Models\Prodi;
use App\Models\SoftwareDetail;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Penjadwalan';

    protected static ?string $modelLabel = 'Mata Kuliah';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Dasar')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Kode Mata Kuliah')
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->placeholder('TI101')
                            ->helperText('Kode unik untuk mata kuliah'),

                        Forms\Components\TextInput::make('name')
                            ->label('Nama Mata Kuliah')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('sks')
                            ->label('SKS')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(6)
                            ->helperText('Jumlah SKS (1-6). Menentukan durasi jadwal.'),

                        Forms\Components\TextInput::make('semester')
                            ->label('Semester')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(8)
                            ->placeholder('1-8')
                            ->helperText('Semester dimana matkul diajarkan'),

                        Forms\Components\Select::make('prodi_id')
                            ->label('Program Studi')
                            ->relationship('prodi', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Program Studi')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('code')
                                    ->label('Kode Prodi')
                                    ->maxLength(10)
                                    ->helperText('Opsional: Kode singkat untuk program studi'),
                            ])
                            ->helperText('Pilih program studi atau buat baru'),

                        Forms\Components\TextInput::make('jumlah_mahasiswa')
                            ->label('Jumlah Mahasiswa')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Jumlah mahasiswa yang mengambil matkul ini. Digunakan untuk filter kapasitas lab.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Kebutuhan Software')
                    ->description('Software yang diperlukan untuk mata kuliah ini')
                    ->schema([
                        Forms\Components\Select::make('software')
                            ->label('Software yang Dibutuhkan')
                            ->relationship('software', 'nama')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Pilih software yang dibutuhkan. Lab yang tidak memiliki software ini tidak akan muncul di pilihan penjadwalan.'),

                        // Legacy field untuk backward compatibility
                        Forms\Components\Select::make('software_requirements')
                            ->label('Software Tambahan (Legacy)')
                            ->options(function () {
                                return \App\Models\Inventory::where('inventoriable_type', 'App\Models\SoftwareDetail')
                                    ->whereNotNull('nama_barang')
                                    ->where('nama_barang', '!=', '')
                                    ->pluck('nama_barang', 'nama_barang')
                                    ->unique()
                                    ->toArray();
                            })
                            ->multiple()
                            ->searchable()
                            ->helperText('Field legacy - gunakan "Software yang Dibutuhkan" untuk penjadwalan otomatis'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Mata Kuliah')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sks')
                    ->label('SKS')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('prodi.name')
                    ->label('Program Studi')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Tidak ada'),

                Tables\Columns\TextColumn::make('software_count')
                    ->label('Software Dibutuhkan')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        if (empty($record->software_requirements)) {
                            return '0';
                        }
                        $count = count($record->software_requirements);
                        return $count . ' Software';
                    })
                    ->color('info')
                    ->tooltip(function ($record) {
                        if (empty($record->software_requirements)) {
                            return 'Tidak ada software yang dibutuhkan';
                        }
                        return 'Software: ' . collect($record->software_requirements)->join(', ');
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('prodi_id')
                    ->label('Program Studi')
                    ->relationship('prodi', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('software_requirements')
                    ->label('Software')
                    ->options(function () {
                        // Ambil daftar software dari inventaris untuk filter
                        return \App\Models\Inventory::where('inventoriable_type', 'App\Models\SoftwareDetail')
                            ->whereNotNull('nama_barang')
                            ->where('nama_barang', '!=', '')
                            ->pluck('nama_barang', 'nama_barang')
                            ->unique()
                            ->toArray();
                    })
                    ->searchable()
                    ->multiple()
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['values'])) {
                            $query->where(function ($query) use ($data) {
                                foreach ($data['values'] as $software) {
                                    $query->orWhereJsonContains('software_requirements', $software);
                                }
                            });
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'edit' => Pages\EditCourse::route('/{record}/edit'),
        ];
    }
}
