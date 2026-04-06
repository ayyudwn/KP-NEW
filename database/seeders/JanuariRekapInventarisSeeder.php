<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\PcRekap;
use App\Models\HeadsetRekap;
use App\Models\WebcamRekap;
use App\Models\BarangPinjam;
use App\Models\BarangNonPc;
use App\Models\SpesifikUtama;
use App\Models\PergantianBarang;
use App\Models\RekapInventarisPeriode;

class JanuariRekapInventarisSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $now = now();

            $periode = RekapInventarisPeriode::firstOrCreate(
                [
                    'bulan' => 1,
                    'tahun' => 2026,
                ],
                [
                    'nama_periode' => 'Januari 2026',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            // Hapus dulu data Januari kalau seeder dijalankan ulang
            SpesifikUtama::where('rekap_inventaris_periode_id', $periode->id)->delete();
            BarangNonPc::where('rekap_inventaris_periode_id', $periode->id)->delete();
            PcRekap::where('rekap_inventaris_periode_id', $periode->id)->delete();
            HeadsetRekap::where('rekap_inventaris_periode_id', $periode->id)->delete();
            WebcamRekap::where('rekap_inventaris_periode_id', $periode->id)->delete();
            BarangPinjam::where('rekap_inventaris_periode_id', $periode->id)->delete();
            PergantianBarang::where('rekap_inventaris_periode_id', $periode->id)->delete();

            /**
             * =========================
             * 1. SPESIFIK UTAMA
             * =========================
             */

            $spesifikUtama = [];

            // B01 - B42
            for ($i = 1; $i <= 42; $i++) {
                $spesifikUtama[] = [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'no' => 'B' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'motherboard' => 'Gigabyte B360M DS3H',
                    'processor' => 'INTEL i7 Core 8700 3,2GHz',
                    'hardisk' => '500GB SSD NVMe M.2 Kingston',
                    'vga' => 'Radeon RX550 2GB',
                    'ram' => '2 x 8 GB DDR4',
                    'dvd' => '-',
                    'key_mouse' => 'Logitech USB',
                    'monitor' => 'LG LED 22” WideScreen',
                    'keterangan' => 'Baik',
                    'posisi' => 'Client',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // B43
            $spesifikUtama[] = [
                'rekap_inventaris_periode_id' => $periode->id,
                'no' => 'B43',
                'motherboard' => 'Gigabyte B360M DS3H',
                'processor' => 'INTEL i7 Core 8700 3,2GHz',
                'hardisk' => '500GB SSD NVMe M.2 Kingston',
                'vga' => 'Radeon RX550 2GB',
                'ram' => '2 x 8 GB DDR4',
                'dvd' => '-',
                'key_mouse' => 'Logitech USB',
                'monitor' => 'LG LED 22” WideScreen',
                'keterangan' => 'Baik',
                'posisi' => 'Laboran',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // B44
            $spesifikUtama[] = [
                'rekap_inventaris_periode_id' => $periode->id,
                'no' => 'B44',
                'motherboard' => 'Gigabyte B360M DS3H',
                'processor' => 'INTEL i7 Core 8700 3,2GHz',
                'hardisk' => '500GB SSD NVMe M.2 Kingston',
                'vga' => 'Radeon RX550 2GB',
                'ram' => '2 x 8 GB DDR4',
                'dvd' => '-',
                'key_mouse' => 'Logitech USB',
                'monitor' => 'LG LED 22” WideScreen',
                'keterangan' => 'Pinjam P.Salam',
                'posisi' => 'Pinjam',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // B45
            $spesifikUtama[] = [
                'rekap_inventaris_periode_id' => $periode->id,
                'no' => 'B45',
                'motherboard' => 'Gigabyte B360M DS3H',
                'processor' => 'INTEL i7 Core 8700 3,2GHz',
                'hardisk' => '500GB SSD NVMe M.2 Kingston',
                'vga' => 'NVIDIA GT730',
                'ram' => '2 x 8 GB DDR4',
                'dvd' => '-',
                'key_mouse' => 'Logitech USB',
                'monitor' => 'LG LED 22” WideScreen',
                'keterangan' => 'Baik',
                'posisi' => 'Dosen',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            SpesifikUtama::insert($spesifikUtama);

            /**
             * =========================
             * 2. BARANG NON-PC
             * =========================
             */
            BarangNonPc::insert([
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Papan tulis (whiteboard)',
                    'jumlah' => 1,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Proyektor Hitachi',
                    'jumlah' => 1,
                    'kondisi' => 'Baik',
                    'keterangan' => 'Pengganti Proyektor SONY',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Layar proyektor BEST',
                    'jumlah' => 1,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Video Switcher ATEN 4-to-1',
                    'jumlah' => 1,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'VGA Switch + 2 kabel VGA',
                    'jumlah' => 1,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Amplifier TOA ZA-301 & speaker ruangan',
                    'jumlah' => 1,
                    'kondisi' => 'Baik',
                    'keterangan' => '1 set',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Microphone SHURE + kabel',
                    'jumlah' => 1,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Speaker Altec Lansing',
                    'jumlah' => 1,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Switch TP-Link 8 port Gigabit (Client)',
                    'jumlah' => 14,
                    'kondisi' => 'Baik',
                    'keterangan' => 'Semua Baru Agustus',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Switch TP-Link 16 port 10/100Mbps',
                    'jumlah' => 2,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'SSD RX7 NVME',
                    'jumlah' => 1,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Kursi Chitose',
                    'jumlah' => 47,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Mouse Pad Hitam',
                    'jumlah' => 44,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Mouse Pad Biru',
                    'jumlah' => 0,
                    'kondisi' => null,
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'AC Daikin',
                    'jumlah' => 1,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'AC Daikin',
                    'jumlah' => 1,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Meja PC',
                    'jumlah' => 47,
                    'kondisi' => 'Baik',
                    'keterangan' => '1 kondisi mengenaskan, 1 kondisi ada yang lepas',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Kabinet laci, 3 tingkat',
                    'jumlah' => 1,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Jam dinding',
                    'jumlah' => 0,
                    'kondisi' => null,
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'baterai AA',
                    'jumlah' => 4,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'baterai AAA',
                    'jumlah' => 4,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'baterai charger',
                    'jumlah' => 1,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Contact Cleaner',
                    'jumlah' => 0,
                    'kondisi' => null,
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'mic wireless sennheiser',
                    'jumlah' => 2,
                    'kondisi' => 'Baik',
                    'keterangan' => '1 Rusak (Jatuh)',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'mic kabel',
                    'jumlah' => 1,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Air Purifier',
                    'jumlah' => 1,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Headset 4TECH',
                    'jumlah' => 35,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Headset Sades',
                    'jumlah' => 11,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            /**
             * =========================
             * 3. PC
             * =========================
             */
            PcRekap::insert([
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'posisi' => 'Client',
                    'jumlah' => 42,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'posisi' => 'Dosen',
                    'jumlah' => 1,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'posisi' => 'Laboran',
                    'jumlah' => 1,
                    'kondisi' => 'Baik',
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            /**
             * =========================
             * 4. HEADSET
             * =========================
             */
            HeadsetRekap::insert([
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'posisi' => 'Client',
                    'jumlah' => 46,
                    'kondisi' => 'Baik',
                    'keterangan' => '1 rusak',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'posisi' => 'Dosen',
                    'jumlah' => 0,
                    'kondisi' => null,
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'posisi' => 'Laboran',
                    'jumlah' => 0,
                    'kondisi' => null,
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            /**
             * =========================
             * 5. WEBCAM
             * =========================
             */
            WebcamRekap::insert([
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'posisi' => 'Client',
                    'jumlah' => 42,
                    'kondisi' => 'Baik',
                    'keterangan' => '5 Rusak',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'posisi' => 'Dosen',
                    'jumlah' => 0,
                    'kondisi' => null,
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'posisi' => 'Laboran',
                    'jumlah' => 0,
                    'kondisi' => null,
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            /**
             * =========================
             * 6. BARANG PINJAM
             * =========================
             */
            BarangPinjam::insert([
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'PC,Monitor,Mouse,Keyboard',
                    'posisi_asal' => 'Lab B',
                    'jumlah' => 1,
                    'keterangan' => 'Pinjam P.Salam',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Hardisk',
                    'posisi_asal' => 'LABORAN',
                    'jumlah' => 1,
                    'keterangan' => 'Pinjam Fauzi (diganti SSD NVME)',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            /**
             * =========================
             * 7. PERGANTIAN BARANG
             * =========================
             */
            PergantianBarang::insert([
                [
                    'rekap_inventaris_periode_id' => $periode->id,
                    'nama_barang' => 'Mousepad',
                    'posisi_asal' => null,
                    'jumlah' => 2,
                    'keterangan' => 'Kondisi: baru. pengadaan barang',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        });
    }
}