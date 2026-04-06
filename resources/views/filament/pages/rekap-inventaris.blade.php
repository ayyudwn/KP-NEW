<x-filament-panels::page>
    <div class="mb-6">
        <div class="rounded-xl border p-4 bg-white dark:bg-gray-900">
            <h2 class="text-xl font-bold">
                Rekap Inventaris Periode {{ $this->periodeLabel }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Klik kode pada kolom Spek untuk melihat detail spesifikasi dalam dialog.
            </p>
        </div>
    </div>

    <div class="space-y-8">
        @livewire(
            'rekap-inventaris.pc-table',
            [
                'periodeId' => $this->periodeId,
                'bulan' => $this->bulan,
                'tahun' => $this->tahun,
            ],
            key('pc-table-' . $this->periodeId)
        )
    </div>
</x-filament-panels::page>