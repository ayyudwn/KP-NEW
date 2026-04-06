<?php

namespace App\Filament\Pages;

use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use App\Models\RekapInventarisPc;
use App\Models\RekapInventarisSpec;
use App\Models\RekapInventarisPeriode;
use App\Models\RekapInventarisSpecDetail;

class RekapInventaris extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';
    protected static string $view = 'filament.pages.rekap-inventaris';
    protected static ?string $title = 'Rekap Inventaris';
    protected static ?string $slug = 'rekap-inventaris';

    public ?int $periodeId = null;
    public ?int $bulan = null;
    public ?int $tahun = null;

    public function mount(): void
    {
        $now = Carbon::now();

        $this->bulan = (int) request()->query('bulan', $now->month);
        $this->tahun = (int) request()->query('tahun', $now->year);

        $periode = $this->getOrCreatePeriode($this->bulan, $this->tahun);
        $this->periodeId = $periode->id;
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super_admin');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sebelumnya')
                ->label('Sebelumnya')
                ->icon('heroicon-o-chevron-left')
                ->url(fn () => static::getUrl([
                    'bulan' => $this->getPreviousMonth()['bulan'],
                    'tahun' => $this->getPreviousMonth()['tahun'],
                ])),

            Action::make('selanjutnya')
                ->label('Selanjutnya')
                ->icon('heroicon-o-chevron-right')
                ->url(fn () => static::getUrl([
                    'bulan' => $this->getNextMonth()['bulan'],
                    'tahun' => $this->getNextMonth()['tahun'],
                ])),

            Action::make('copyBulanSebelumnya')
                ->label('Copy dari Bulan Sebelumnya')
                ->icon('heroicon-o-document-duplicate')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Copy Data Bulan Sebelumnya')
                ->modalDescription('Semua data PC dan detail spek dari bulan sebelumnya akan disalin ke bulan aktif. Proses ini hanya bisa dilakukan jika bulan aktif masih kosong.')
                ->modalSubmitActionLabel('Ya, Copy Data')
                ->action(function () {
                    $currentPeriod = RekapInventarisPeriode::findOrFail($this->periodeId);

                    if (! $this->isPeriodCompletelyEmpty($currentPeriod->id)) {
                        Notification::make()
                            ->title('Copy dibatalkan')
                            ->body('Periode ini sudah memiliki data. Hapus dulu isi halamannya jika ingin copy ulang.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $previousPeriod = $this->getPreviousPeriod($currentPeriod);

                    if (! $previousPeriod) {
                        Notification::make()
                            ->title('Copy gagal')
                            ->body('Periode sebelumnya tidak ditemukan.')
                            ->warning()
                            ->send();

                        return;
                    }

                    if ($this->isPeriodCompletelyEmpty($previousPeriod->id)) {
                        Notification::make()
                            ->title('Copy gagal')
                            ->body('Periode sebelumnya ada, tetapi tidak memiliki data untuk disalin.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $this->copyAllDataFromPeriod($previousPeriod->id, $currentPeriod->id);

                    Notification::make()
                        ->title('Copy berhasil')
                        ->body("Data dari {$previousPeriod->nama_periode} berhasil disalin ke {$currentPeriod->nama_periode}.")
                        ->success()
                        ->send();

                    $this->redirect(static::getUrl([
                        'bulan' => $this->bulan,
                        'tahun' => $this->tahun,
                    ]));
                }),

            Action::make('hapusIsiHalaman')
                ->label('Hapus Isi Halaman')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Hapus Semua Isi Halaman?')
                ->modalDescription(fn () => "Semua data pada periode {$this->periodeLabel} akan dihapus. Tindakan ini tidak bisa dibatalkan.")
                ->modalSubmitActionLabel('Ya, Hapus Semua Isi Halaman')
                ->action(function () {
                    if ($this->isPeriodCompletelyEmpty($this->periodeId)) {
                        Notification::make()
                            ->title('Tidak ada data')
                            ->body("Periode {$this->periodeLabel} sudah kosong.")
                            ->warning()
                            ->send();

                        return;
                    }

                    $this->deleteAllDataInPeriod($this->periodeId);

                    Notification::make()
                        ->title('Berhasil dihapus')
                        ->body("Semua isi periode {$this->periodeLabel} berhasil dihapus.")
                        ->success()
                        ->send();

                    $this->redirect(static::getUrl([
                        'bulan' => $this->bulan,
                        'tahun' => $this->tahun,
                    ]));
                }),
        ];
    }

    public function getPeriodeLabelProperty(): string
    {
        return $this->getNamaPeriode($this->bulan, $this->tahun);
    }

    protected function getPreviousMonth(): array
    {
        $date = Carbon::create($this->tahun, $this->bulan, 1)->subMonth();

        return [
            'bulan' => $date->month,
            'tahun' => $date->year,
        ];
    }

    protected function getNextMonth(): array
    {
        $date = Carbon::create($this->tahun, $this->bulan, 1)->addMonth();

        return [
            'bulan' => $date->month,
            'tahun' => $date->year,
        ];
    }

    protected function getOrCreatePeriode(int $bulan, int $tahun): RekapInventarisPeriode
    {
        return RekapInventarisPeriode::firstOrCreate(
            [
                'bulan' => $bulan,
                'tahun' => $tahun,
            ],
            [
                'nama_periode' => $this->getNamaPeriode($bulan, $tahun),
            ]
        );
    }

    protected function getPreviousPeriod(RekapInventarisPeriode $currentPeriod): ?RekapInventarisPeriode
    {
        return RekapInventarisPeriode::query()
            ->where(function ($query) use ($currentPeriod) {
                $query->where('tahun', '<', $currentPeriod->tahun)
                    ->orWhere(function ($subQuery) use ($currentPeriod) {
                        $subQuery->where('tahun', $currentPeriod->tahun)
                            ->where('bulan', '<', $currentPeriod->bulan);
                    });
            })
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->first();
    }

    protected function isPeriodCompletelyEmpty(int $periodeId): bool
    {
        return RekapInventarisPc::query()
            ->where('rekap_inventaris_periode_id', $periodeId)
            ->count() === 0
            && RekapInventarisSpec::query()
                ->where('rekap_inventaris_periode_id', $periodeId)
                ->count() === 0;
    }

    protected function copyAllDataFromPeriod(int $fromPeriodeId, int $toPeriodeId): void
    {
        DB::transaction(function () use ($fromPeriodeId, $toPeriodeId) {
            $specMap = [];

            $oldSpecs = RekapInventarisSpec::query()
                ->where('rekap_inventaris_periode_id', $fromPeriodeId)
                ->with('details')
                ->orderBy('urutan_kode')
                ->get();

            foreach ($oldSpecs as $oldSpec) {
                $newSpec = RekapInventarisSpec::create([
                    'rekap_inventaris_periode_id' => $toPeriodeId,
                    'kode_spek' => $oldSpec->kode_spek,
                    'urutan_kode' => $oldSpec->urutan_kode,
                    'fingerprint' => md5($toPeriodeId . '|' . $oldSpec->fingerprint),
                ]);

                foreach ($oldSpec->details as $detail) {
                    RekapInventarisSpecDetail::create([
                        'rekap_inventaris_spec_id' => $newSpec->id,
                        'komponen' => $detail->komponen,
                        'detail' => $detail->detail,
                        'kondisi' => $detail->kondisi,
                        'catatan_kondisi' => $detail->catatan_kondisi,
                        'urutan' => $detail->urutan,
                    ]);
                }

                $specMap[$oldSpec->id] = $newSpec->id;
            }

            $oldPcs = RekapInventarisPc::query()
                ->where('rekap_inventaris_periode_id', $fromPeriodeId)
                ->orderBy('id')
                ->get();

            foreach ($oldPcs as $oldPc) {
                RekapInventarisPc::create([
                    'rekap_inventaris_periode_id' => $toPeriodeId,
                    'rekap_inventaris_spec_id' => $specMap[$oldPc->rekap_inventaris_spec_id] ?? null,
                    'no_pc' => $oldPc->no_pc,
                    'lokasi' => $oldPc->lokasi,
                    'kondisi' => $oldPc->kondisi,
                ]);
            }
        });
    }

    protected function deleteAllDataInPeriod(int $periodeId): void
    {
        DB::transaction(function () use ($periodeId) {
            RekapInventarisPc::query()
                ->where('rekap_inventaris_periode_id', $periodeId)
                ->delete();

            RekapInventarisSpec::query()
                ->where('rekap_inventaris_periode_id', $periodeId)
                ->delete();
        });
    }

    protected function getNamaPeriode(int $bulan, int $tahun): string
    {
        $namaBulan = [
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
        ];

        return ($namaBulan[$bulan] ?? 'Bulan Tidak Valid') . ' ' . $tahun;
    }
}