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
                        Forms\Components\Select::make('prodi_id')
                            ->label('Program Studi')
                            ->relationship('prodi', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                // Update kode matkul saat prodi berubah
                                $codeNumber = $get('code_number');
                                if ($state && $codeNumber) {
                                    $prodi = \App\Models\Prodi::find($state);
                                    if ($prodi && $prodi->code) {
                                        $set('code', $prodi->code . '.' . $codeNumber);
                                    }
                                } elseif (!$state) {
                                    $set('code', $codeNumber);
                                }
                            })
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Program Studi')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('code')
                                    ->label('Kode Prodi')
                                    ->required()
                                    ->maxLength(10)
                                    ->helperText('Kode prodi wajib diisi (contoh: A11)'),
                            ])
                            ->helperText('Pilih program studi terlebih dahulu'),

                        Forms\Components\TextInput::make('code_number')
                            ->label('Kode Angka Mata Kuliah')
                            ->required()
                            ->numeric()
                            ->maxLength(10)
                            ->placeholder('64504')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                // Generate kode matkul lengkap
                                $prodiId = $get('prodi_id');
                                if ($prodiId && $state) {
                                    $prodi = \App\Models\Prodi::find($prodiId);
                                    if ($prodi && $prodi->code) {
                                        $set('code', $prodi->code . '.' . $state);
                                    } else {
                                        $set('code', $state);
                                    }
                                } else {
                                    $set('code', $state);
                                }
                            })
                            ->helperText('Masukkan kode angka saja (contoh: 64504)'),

                        Forms\Components\TextInput::make('code')
                            ->label('Kode Mata Kuliah (Otomatis)')
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('A11.64504')
                            ->helperText('Kode lengkap otomatis: KodeProdi.KodeAngka'),

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
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Kebutuhan Software')
                    ->description('Software yang diperlukan untuk mata kuliah ini')
                    ->schema([
                        Forms\Components\Select::make('software')
                            ->label('Software yang Dibutuhkan')
                            ->relationship(
                                name: 'software',
                                titleAttribute: 'nama',
                                modifyQueryUsing: fn($query) => $query->whereNotNull('code')
                            )
                            ->getOptionLabelFromRecordUsing(fn($record) => "[{$record->code}] {$record->nama}")
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Pilih software dari daftar master. Lab yang tidak memiliki software ini tidak akan muncul di pilihan penjadwalan.')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('code')
                                    ->label('Kode Software')
                                    ->required()
                                    ->unique('software_details', 'code')
                                    ->maxLength(50)
                                    ->placeholder('PREMIERE'),
                                Forms\Components\TextInput::make('nama')
                                    ->label('Nama Software')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Adobe Premiere Pro'),
                            ])
                            ->createOptionUsing(function (array $data) {
                                return \App\Models\SoftwareDetail::create($data)->id;
                            }),
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

                Tables\Columns\TextColumn::make('code')
                    ->label('Kode Mata Kuliah')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Tidak ada'),

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
                            ->filter(fn($value, $key) => !empty($key) && !empty($value))
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
            'index' => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'edit' => Pages\EditCourse::route('/{record}/edit'),
        ];
    }
}
