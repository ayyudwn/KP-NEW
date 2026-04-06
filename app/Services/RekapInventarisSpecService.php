<?php

namespace App\Services;

use App\Models\RekapInventarisPeriode;
use App\Models\RekapInventarisPc;
use App\Models\RekapInventarisSpec;
use App\Models\RekapInventarisSpecDetail;
use Illuminate\Support\Facades\DB;

class RekapInventarisSpecService
{
    public const KOMPONEN = [
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

    public function findOrCreate(int $periodeId, array $details): RekapInventarisSpec
    {
        $normalizedForStore = $this->normalizeDetailsForStore($details);
        $fingerprint = $this->makeFingerprint($details);

        $existing = RekapInventarisSpec::query()
            ->where('rekap_inventaris_periode_id', $periodeId)
            ->where('fingerprint', $fingerprint)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($periodeId, $normalizedForStore, $fingerprint) {
            $periode = RekapInventarisPeriode::findOrFail($periodeId);

            $spec = RekapInventarisSpec::create([
                'rekap_inventaris_periode_id' => $periodeId,
                'kode_spek' => 'TMP-' . $periode->tahun . '-' . uniqid(),
                'urutan_kode' => 9999,
                'fingerprint' => $fingerprint,
            ]);

            foreach ($normalizedForStore as $index => $row) {
                RekapInventarisSpecDetail::create([
                    'rekap_inventaris_spec_id' => $spec->id,
                    'komponen' => $row['komponen'],
                    'detail' => $row['detail'],
                    'kondisi' => $row['kondisi'],
                    'catatan_kondisi' => $row['catatan_kondisi'],
                    'urutan' => $index + 1,
                ]);
            }

            return $spec;
        });
    }

    public function fingerprintFromDetails(array $details): string
    {
        return $this->makeFingerprint($details);
    }

    public function normalizePeriodSpecs(int $periodeId): void
    {
        DB::transaction(function () use ($periodeId) {
            $pcs = RekapInventarisPc::query()
                ->where('rekap_inventaris_periode_id', $periodeId)
                ->with('spec.details')
                ->orderBy('id')
                ->get();

            foreach ($pcs as $pc) {
                if (! $pc->spec) {
                    continue;
                }

                $details = [];
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

                foreach ($pc->spec->details as $detail) {
                    $index = $map[$detail->komponen] ?? null;

                    if ($index) {
                        $details[$index] = [
                            'detail' => $detail->detail,
                            'kondisi' => $detail->kondisi,
                            'catatan_kondisi' => $detail->catatan_kondisi,
                        ];
                    }
                }

                $canonicalSpec = $this->findOrCreate($periodeId, $details);

                if ((int) $pc->rekap_inventaris_spec_id !== (int) $canonicalSpec->id) {
                    $pc->update([
                        'rekap_inventaris_spec_id' => $canonicalSpec->id,
                    ]);
                }
            }

            $this->syncPeriodSpecOrder($periodeId);
        });
    }

    public function syncPeriodSpecOrder(int $periodeId): void
    {
        DB::transaction(function () use ($periodeId) {
            $this->cleanupUnusedSpecs($periodeId);
            $this->reindexSpecCodes($periodeId);
        });
    }

    protected function cleanupUnusedSpecs(int $periodeId): void
    {
        $usedSpecIds = RekapInventarisPc::query()
            ->where('rekap_inventaris_periode_id', $periodeId)
            ->whereNotNull('rekap_inventaris_spec_id')
            ->pluck('rekap_inventaris_spec_id')
            ->unique()
            ->values();

        $query = RekapInventarisSpec::query()
            ->where('rekap_inventaris_periode_id', $periodeId);

        if ($usedSpecIds->isNotEmpty()) {
            $query->whereNotIn('id', $usedSpecIds);
        }

        $query->delete();
    }

    protected function reindexSpecCodes(int $periodeId): void
    {
        $periode = RekapInventarisPeriode::findOrFail($periodeId);

        $pcs = RekapInventarisPc::query()
            ->where('rekap_inventaris_periode_id', $periodeId)
            ->whereNotNull('rekap_inventaris_spec_id')
            ->orderBy('id')
            ->get();

        $orderedUniqueSpecIds = [];
        $seen = [];

        foreach ($pcs as $pc) {
            $specId = $pc->rekap_inventaris_spec_id;

            if (! isset($seen[$specId])) {
                $seen[$specId] = true;
                $orderedUniqueSpecIds[] = $specId;
            }
        }

        if (empty($orderedUniqueSpecIds)) {
            return;
        }

        foreach ($orderedUniqueSpecIds as $specId) {
            RekapInventarisSpec::query()
                ->where('id', $specId)
                ->where('rekap_inventaris_periode_id', $periodeId)
                ->update([
                    'kode_spek' => "TMP-{$periodeId}-{$specId}",
                    'urutan_kode' => 9999,
                ]);
        }

        foreach ($orderedUniqueSpecIds as $index => $specId) {
            $order = $index + 1;
            $suffix = $this->numberToLetters($order);

            RekapInventarisSpec::query()
                ->where('id', $specId)
                ->where('rekap_inventaris_periode_id', $periodeId)
                ->update([
                    'urutan_kode' => $order,
                    'kode_spek' => "D2B/{$periode->tahun}{$suffix}",
                ]);
        }
    }

    protected function makeFingerprint(array $details): string
    {
        $normalized = [];

        foreach (self::KOMPONEN as $index => $komponen) {
            $normalized[] = [
                'komponen' => $komponen,
                'detail' => trim((string) ($details[$index]['detail'] ?? '')),
                'kondisi' => $details[$index]['kondisi'] ?? null,
                'catatan_kondisi' => trim((string) ($details[$index]['catatan_kondisi'] ?? '')),
            ];
        }

        return md5(json_encode($normalized));
    }

    protected function normalizeDetailsForStore(array $details): array
    {
        $result = [];

        foreach (self::KOMPONEN as $index => $komponen) {
            $result[] = [
                'komponen' => $komponen,
                'detail' => trim((string) ($details[$index]['detail'] ?? '')),
                'kondisi' => $details[$index]['kondisi'] ?? null,
                'catatan_kondisi' => trim((string) ($details[$index]['catatan_kondisi'] ?? '')),
            ];
        }

        return $result;
    }

    protected function numberToLetters(int $number): string
    {
        $result = '';

        while ($number > 0) {
            $number--;
            $result = chr(65 + ($number % 26)) . $result;
            $number = intdiv($number, 26);
        }

        return $result;
    }
}