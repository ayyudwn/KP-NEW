<?php

namespace App\Livewire\RekapInventaris;

use App\Models\RekapInventarisPc;
use Filament\Forms\Get;
use Filament\Tables;
use Filament\Tables\Table;
use Livewire\Component;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Notifications\Notification;

class PcTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public int $periodeId;
    public int $bulan;
    public int $tahun;

    public function mount(int $periodeId, int $bulan, int $tahun): void
    {
        $this->periodeId = $periodeId;
        $this->bulan = $bulan;
        $this->tahun = $tahun;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                RekapInventarisPc::query()
                    ->where('rekap_inventaris_periode_id', $this->periodeId)
                    ->with('spec.details')
                    ->orderByRaw('CAST(SUBSTRING(no_pc, 2) AS UNSIGNED)')
            )
            ->heading('Rekap PC')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah PC')
                    ->modalWidth('7xl')
                    ->form($this->getPcFormSchema(isEdit: false))
                    ->using(function (array $data) {
                        $periodeId = $this->periodeId;
                        $service = resolve(\App\Services\RekapInventarisSpecService::class);

                        $spec = $service->findOrCreate(
                            $periodeId,
                            $data['spec_details'] ?? []
                        );

                        $pc = RekapInventarisPc::create([
                            'rekap_inventaris_periode_id' => $periodeId,
                            'rekap_inventaris_spec_id' => $spec->id,
                            'no_pc' => $this->generateNextNoPc(),
                            'lokasi' => $data['lokasi'],
                            'kondisi' => $data['kondisi'],
                        ]);

                        $service->syncPeriodSpecOrder($periodeId);

                        return $pc->fresh();
                    }),
            ])
            ->columns([
                TextColumn::make('id')
                    ->label('No')
                    ->rowIndex(),

                TextColumn::make('no_pc')
                    ->label('NoPC')
                    ->searchable(),

                TextColumn::make('spec.kode_spek')
                    ->label('Spek')
                    ->color('primary')
                    ->action(
                        Tables\Actions\Action::make('lihatSpek')
                            ->modalHeading(fn (RekapInventarisPc $record) => 'Detail Spesifikasi - ' . ($record->spec?->kode_spek ?? '-'))
                            ->modalWidth('5xl')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Tutup')
                            ->infolist(fn (RekapInventarisPc $record) => [
                                \Filament\Infolists\Components\Section::make($record->spec?->kode_spek ?? 'Detail Spesifikasi')
                                    ->schema(
                                        collect($record->spec?->details ?? [])->map(function ($detail) {
                                            return \Filament\Infolists\Components\Grid::make(4)->schema([
                                                \Filament\Infolists\Components\TextEntry::make('komponen_' . $detail->id)
                                                    ->label('')
                                                    ->state($detail->komponen),

                                                \Filament\Infolists\Components\TextEntry::make('detail_' . $detail->id)
                                                    ->label('Detail')
                                                    ->state($detail->detail ?: '-'),

                                                \Filament\Infolists\Components\TextEntry::make('kondisi_' . $detail->id)
                                                    ->label('Kondisi')
                                                    ->badge()
                                                    ->state($detail->kondisi ?: '-'),

                                                \Filament\Infolists\Components\TextEntry::make('catatan_' . $detail->id)
                                                    ->label('Keterangan')
                                                    ->state($detail->catatan_kondisi ?: '-'),
                                            ]);
                                        })->toArray()
                                    ),
                            ])
                    ),

                TextColumn::make('lokasi')
                    ->label('Lokasi')
                    ->badge(),

                TextColumn::make('kondisi')
                    ->label('Kondisi')
                    ->badge(),
            ])
            ->actions([
                Tables\Actions\Action::make('copyPrev')
                    ->label('Copy ke Baris Sebelumnya')
                    ->icon('heroicon-o-arrow-up')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Copy ke Baris Sebelumnya?')
                    ->modalDescription('Data akan dicopy ke nomor PC sebelumnya jika slot tersebut belum ada.')
                    ->modalSubmitActionLabel('Ya, Copy')
                    ->action(function (RekapInventarisPc $record): void {
                        $currentNumber = $this->extractNoPcNumber($record->no_pc);
                        $prevNumber = $currentNumber - 1;

                        if ($prevNumber <= 0) {
                            Notification::make()
                                ->title('Gagal')
                                ->body('Tidak bisa copy ke bawah B01.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $prevNoPc = $this->formatNoPc($prevNumber);

                        $exists = RekapInventarisPc::query()
                            ->where('rekap_inventaris_periode_id', $this->periodeId)
                            ->where('no_pc', $prevNoPc)
                            ->exists();

                        if ($exists) {
                            Notification::make()
                                ->title('Gagal')
                                ->body("{$prevNoPc} sudah tersedia dan tidak bisa dicopy ke baris sebelumnya.")
                                ->danger()
                                ->send();
                            return;
                        }

                        RekapInventarisPc::create([
                            'rekap_inventaris_periode_id' => $this->periodeId,
                            'rekap_inventaris_spec_id' => $record->rekap_inventaris_spec_id,
                            'no_pc' => $prevNoPc,
                            'lokasi' => $record->lokasi,
                            'kondisi' => $record->kondisi,
                        ]);

                        resolve(\App\Services\RekapInventarisSpecService::class)
                            ->syncPeriodSpecOrder($this->periodeId);

                        Notification::make()
                            ->title('Berhasil')
                            ->body("Data berhasil dicopy ke {$prevNoPc}.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('copyNext')
                    ->label('Copy ke Baris Berikutnya')
                    ->icon('heroicon-o-arrow-down')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Copy ke Baris Berikutnya?')
                    ->modalDescription('Data akan dicopy ke nomor PC berikutnya jika slot tersebut belum ada.')
                    ->modalSubmitActionLabel('Ya, Copy')
                    ->action(function (RekapInventarisPc $record): void {
                        $currentNumber = $this->extractNoPcNumber($record->no_pc);
                        $nextNumber = $currentNumber + 1;
                        $nextNoPc = $this->formatNoPc($nextNumber);

                        $exists = RekapInventarisPc::query()
                            ->where('rekap_inventaris_periode_id', $this->periodeId)
                            ->where('no_pc', $nextNoPc)
                            ->exists();

                        if ($exists) {
                            Notification::make()
                                ->title('Gagal')
                                ->body("{$nextNoPc} sudah tersedia dan tidak bisa dicopy ke baris berikutnya.")
                                ->danger()
                                ->send();
                            return;
                        }

                        RekapInventarisPc::create([
                            'rekap_inventaris_periode_id' => $this->periodeId,
                            'rekap_inventaris_spec_id' => $record->rekap_inventaris_spec_id,
                            'no_pc' => $nextNoPc,
                            'lokasi' => $record->lokasi,
                            'kondisi' => $record->kondisi,
                        ]);

                        resolve(\App\Services\RekapInventarisSpecService::class)
                            ->syncPeriodSpecOrder($this->periodeId);

                        Notification::make()
                            ->title('Berhasil')
                            ->body("Data berhasil dicopy ke {$nextNoPc}.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->modalWidth('7xl')
                    ->fillForm(function (RekapInventarisPc $record): array {
                        $details = [];

                        foreach ($record->spec?->details ?? [] as $detail) {
                            $map = [
                                'Motherboard' => 1,
                                'Processor' => 2,
                                'Hardisk' => 3,
                                'VGA' => 4,
                                'RAM' => 5,
                                'DVD' => 6,
                                'Keyboard' => 7,
                                'Mouse' => 8,
                                'Monitor' => 9,
                            ];

                            $index = $map[$detail->komponen] ?? null;

                            if ($index) {
                                $details[$index] = [
                                    'detail' => $detail->detail,
                                    'kondisi' => $detail->kondisi,
                                    'catatan_kondisi' => $detail->catatan_kondisi,
                                ];
                            }
                        }

                        return [
                            'no_pc_preview' => $record->no_pc,
                            'lokasi' => $record->lokasi,
                            'kondisi' => $record->kondisi,
                            'spec_details' => $details,
                        ];
                    })
                    ->form($this->getPcFormSchema(isEdit: true))
                    ->using(function (RekapInventarisPc $record, array $data) {
                        $periodeId = $this->periodeId;
                        $service = resolve(\App\Services\RekapInventarisSpecService::class);

                        $incomingFingerprint = $service->fingerprintFromDetails(
                            $data['spec_details'] ?? []
                        );

                        if (
                            $record->spec &&
                            $record->spec->fingerprint === $incomingFingerprint
                        ) {
                            $record->update([
                                'lokasi' => $data['lokasi'],
                                'kondisi' => $data['kondisi'],
                            ]);

                            $this->updateSpecDetails($record, $data['spec_details'] ?? []);
                        } else {
                            $spec = $service->findOrCreate(
                                $periodeId,
                                $data['spec_details'] ?? []
                            );

                            $record->update([
                                'rekap_inventaris_spec_id' => $spec->id,
                                'lokasi' => $data['lokasi'],
                                'kondisi' => $data['kondisi'],
                            ]);
                        }

                        $service->syncPeriodSpecOrder($periodeId);

                        return $record->fresh();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->after(function () {
                        $periodeId = $this->periodeId;
                        $service = resolve(\App\Services\RekapInventarisSpecService::class);
                        $service->syncPeriodSpecOrder($periodeId);
                    }),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50]);
    }

    protected function getPcFormSchema(bool $isEdit = false): array
    {
        return [
            Grid::make(3)->schema([
                $isEdit
                    ? Placeholder::make('no_pc_preview')
                        ->label('NoPC')
                        ->content(fn ($state) => $state ?: '-')
                    : Placeholder::make('no_pc_info')
                        ->label('NoPC')
                        ->content(fn () => 'Otomatis dibuat saat data disimpan'),

                Select::make('lokasi')
                    ->label('Lokasi')
                    ->options([
                        'Client' => 'Client',
                        'Laboran' => 'Laboran',
                        'Dosen' => 'Dosen',
                    ])
                    ->required(),

                Select::make('kondisi')
                    ->label('Kondisi')
                    ->options([
                        'Baik' => 'Baik',
                        'Rusak' => 'Rusak',
                    ])
                    ->required(),
            ]),

            Section::make('Detail Spesifikasi')
                ->schema($this->getSpecDetailFormSchema())
                ->columns(1),
        ];
    }

    protected function getSpecDetailFormSchema(): array
    {
        $components = [
            1 => 'Motherboard',
            2 => 'Processor',
            3 => 'Hardisk',
            4 => 'VGA',
            5 => 'RAM',
            6 => 'DVD',
            7 => 'Keyboard',
            8 => 'Mouse',
            9 => 'Monitor',
        ];

        $schema = [];

        foreach ($components as $index => $label) {
            $schema[] = Section::make($label)
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make("spec_details.$index.detail")
                            ->label('Detail'),

                        Select::make("spec_details.$index.kondisi")
                            ->label('Kondisi')
                            ->options([
                                'Baik' => 'Baik',
                                'Kurang Baik' => 'Kurang Baik',
                                'Rusak' => 'Rusak',
                            ])
                            ->placeholder('-')
                            ->nullable(),

                        Textarea::make("spec_details.$index.catatan_kondisi")
                            ->label('Keterangan')
                            ->rows(2)
                            ->visible(fn (Get $get) => in_array(
                                $get("spec_details.$index.kondisi"),
                                ['Kurang Baik', 'Rusak']
                            ))
                            ->dehydrated(fn (Get $get) => in_array(
                                $get("spec_details.$index.kondisi"),
                                ['Kurang Baik', 'Rusak']
                            )),
                    ]),
                ]);
        }

        return $schema;
    }

    protected function generateNextNoPc(): string
    {
        $existing = RekapInventarisPc::query()
            ->where('rekap_inventaris_periode_id', $this->periodeId)
            ->pluck('no_pc')
            ->toArray();

        $numbers = array_map(function ($no) {
            return $this->extractNoPcNumber($no);
        }, $existing);

        sort($numbers);

        $expected = 1;

        foreach ($numbers as $num) {
            if ($num !== $expected) {
                break;
            }
            $expected++;
        }

        return $this->formatNoPc($expected);
    }

    protected function extractNoPcNumber(string $noPc): int
    {
        return (int) preg_replace('/[^0-9]/', '', $noPc);
    }

    protected function formatNoPc(int $number): string
    {
        return 'B' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
    }

    protected function updateSpecDetails(RekapInventarisPc $record, array $details): void
    {
        if (! $record->spec) {
            return;
        }

        $map = [
            1 => 'Motherboard',
            2 => 'Processor',
            3 => 'Hardisk',
            4 => 'VGA',
            5 => 'RAM',
            6 => 'DVD',
            7 => 'Keyboard',
            8 => 'Mouse',
            9 => 'Monitor',
        ];

        foreach ($map as $index => $komponen) {
            $detailRecord = $record->spec->details
                ->firstWhere('komponen', $komponen);

            if (! $detailRecord) {
                continue;
            }

            $detailRecord->update([
                'detail' => trim((string) ($details[$index]['detail'] ?? '')),
                'kondisi' => $details[$index]['kondisi'] ?? null,
                'catatan_kondisi' => trim((string) ($details[$index]['catatan_kondisi'] ?? '')),
            ]);
        }
    }

    public function render()
    {
        return view('livewire.rekap-inventaris.pc-table');
    }
}