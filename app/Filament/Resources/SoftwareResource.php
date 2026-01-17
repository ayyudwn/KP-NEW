<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SoftwareResource\Pages;
use App\Models\SoftwareDetail;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SoftwareResource extends Resource
{
    protected static ?string $model = SoftwareDetail::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = 'MASTER DATA';

    protected static ?string $navigationLabel = 'Daftar Software';

    protected static ?string $modelLabel = 'Software';

    protected static ?string $pluralModelLabel = 'Daftar Software';

    protected static ?string $slug = 'software';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Software')
                    ->description('Data master software yang tersentralisasi')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Kode Software')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('PREMIERE')
                            ->helperText('Kode unik untuk identifikasi software (contoh: PREMIERE, VSCODE, XAMPP)')
                            ->regex('/^[A-Z0-9_]+$/')
                            ->validationMessages([
                                'regex' => 'Kode hanya boleh berisi huruf kapital, angka, dan underscore',
                            ]),

                        Forms\Components\TextInput::make('nama')
                            ->label('Nama Software')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Adobe Premiere Pro')
                            ->helperText('Nama lengkap software'),

                        Forms\Components\Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->maxLength(500)
                            ->rows(2)
                            ->placeholder('Deskripsi singkat tentang software ini')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('nama')
                    ->label('Nama Software')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('labs_count')
                    ->label('Digunakan di Lab')
                    ->counts('labs')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('courses_count')
                    ->label('Dibutuhkan Mata Kuliah')
                    ->counts('courses')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code')
            ->filters([
                //
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
            'index' => Pages\ListSoftware::route('/'),
            'create' => Pages\CreateSoftware::route('/create'),
            'edit' => Pages\EditSoftware::route('/{record}/edit'),
        ];
    }
}

