# SIOPAL-UDINUS (Beta)

SIOPAL (Sistem Informasi Operasional dan Pelayanan Administrasi Laboratorium) UDINUS adalah sistem informasi yang dirancang untuk mengelola operasional dan pelayanan administrasi laboratorium di Universitas Dian Nuswantoro (UDINUS). Proyek ini masih dalam tahap beta, artinya sedang dalam pengembangan aktif.

**Peringatan: Proyek web ini masih dalam tahap beta. Fitur dan stabilitas mungkin belum sepenuhnya terjamin. Gunakan dengan risiko Anda sendiri.**

## Fitur Utama (Tahap Pengembangan)

- Manajemen Data Laboratorium: Mengelola data laboratorium, termasuk inventaris alat, spesifikasi, dan lokasi.
- Peminjaman Alat: Memfasilitasi proses peminjaman alat oleh mahasiswa dan staf.
- Penjadwalan: Mengelola jadwal penggunaan laboratorium dan alat.
- Administrasi: Mengelola data pengguna, laporan, dan administrasi lainnya.
- Laporan: Pembuatan laporan terkait penggunaan laboratorium.
- Antarmuka Admin: Antarmuka administratif yang mudah digunakan.

## Teknologi yang Digunakan

- Bahasa Pemrograman: PHP
- Framework: Laravel 12
- Admin Panel: Filament
- Database: MySQL
- Frontend: Blade (Laravel), Tailwind CSS (Filament)

## Persyaratan Sistem

- PHP versi 8.2 atau lebih tinggi
- Composer versi terbaru
- MySQL versi 8.0 atau lebih tinggi
- Node.js dan npm (untuk aset frontend Filament)

## Dokumentasi

Dokumentasi teknis tersedia di folder [`docs/`](docs/):

| Dokumen                                                          | Deskripsi                                                                    |
| ---------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| [Setup Project](docs/SETUP_PROJECT.md)                           | Panduan instalasi dan konfigurasi                                            |
| [Penjadwalan Otomatis](docs/DOKUMENTASI_PENJADWALAN_OTOMATIS.md) | Dokumentasi teknis sistem penjadwalan (constraint filtering, Eloquent query) |
| [Dynamic Break 3 SKS](docs/DYNAMIC_BREAK_3SKS.md)                | Penjelasan kasus khusus break time untuk matkul 3+ SKS siang                 |
| [Arsitektur Data Software](docs/ARSITEKTUR_DATA_SOFTWARE.md)     | Arsitektur relasi data software-lab                                          |

## Catatan Penting

- Proyek ini masih dalam tahap beta, sehingga fitur dan stabilitas dapat berubah.
- Selalu perbarui dependensi Anda ke versi terbaru.
- Periksa dokumentasi Laravel 12 dan Filament secara berkala untuk pembaruan dan perubahan.
- Laporkan setiap bug atau masalah yang ditemukan agar dapat segera diperbaiki.

## Lisensi

Proyek ini dilisensikan di bawah lisensi Proprietary.

Semoga README ini membantu Anda dalam memahami status beta proyek SIOPAL-UDINUS.
