# Dokumentasi Arsitektur Data Software

## Ringkasan

Sistem SIOPAL menggunakan **dua tabel** untuk menyimpan relasi software dengan laboratorium. Dokumen ini menjelaskan perbedaan dan kapan menggunakan masing-masing.

---

## Tabel `lab_software` (Pivot Table)

### Struktur

```sql
lab_software
├── laboratorium_id (FK)
├── software_detail_id (FK)
├── version (string, nullable)
└── timestamps
```

### Fungsi

- **Relasi many-to-many** antara `Laboratorium` ↔ `SoftwareDetail`
- Digunakan untuk **counting** dan **filtering**
- Mendukung fitur **course-lab matching** berdasarkan software requirement

### Digunakan Di

- Kolom "Digunakan di Lab" di menu **Daftar Software**
- Filtering lab berdasarkan software yang tersedia
- Algoritma penjadwalan otomatis

### Akses Via Model

```php
// Dari Laboratorium
$lab->software; // Collection of SoftwareDetail

// Dari SoftwareDetail
$software->labs; // Collection of Laboratorium
```

---

## Tabel `inventories` (Master Table)

### Struktur

```sql
inventories
├── id
├── kode_inventaris (auto-generated: UDN/LABKOM/INV/SOFTWARE/D2A/01)
├── laboratorium_id (FK)
├── inventoriable_type (polymorphic: App\Models\SoftwareDetail)
├── inventoriable_id (FK)
├── nama_barang (string)
└── timestamps
```

### Fungsi

- **Tracking inventaris** dengan kode unik
- **Manajemen lisensi** (nomor lisensi, tanggal kadaluarsa)
- **Audit trail** untuk pencatatan aset resmi

### Digunakan Di

- Menu **Inventaris Software** (per lab)
- Laporan inventaris
- Export data inventaris ke Excel

### Akses Via Model

```php
// Dari Inventory
$inventory->inventoriable; // SoftwareDetail instance
$inventory->laboratorium; // Laboratorium instance

// Query software inventory
Inventory::where('inventoriable_type', SoftwareDetail::class)->get();
```

---

## Kapan Menggunakan Mana?

| Use Case                        | Tabel          | Contoh                              |
| ------------------------------- | -------------- | ----------------------------------- |
| Menghitung software di lab      | `lab_software` | "Lab D2A punya 15 software"         |
| List software untuk penjadwalan | `lab_software` | Matching course ke lab              |
| Detail inventaris per item      | `inventories`  | "D2A memiliki Photoshop versi 2024" |
| Export laporan inventaris       | `inventories`  | Kode inventaris untuk audit         |
| Cek lisensi expired             | `inventories`  | Alert lisensi mau habis             |

---

## Seeder

`LabSoftwareSeeder.php` sudah mengisi **kedua tabel sekaligus** untuk menjaga konsistensi:

```php
// 1. Mengisi tabel inventories
Inventory::create([
    'laboratorium_id' => $lab->id,
    'inventoriable_type' => SoftwareDetail::class,
    'inventoriable_id' => $software->id,
    'nama_barang' => $software->nama,
]);

// 2. Mengisi tabel lab_software
$lab->software()->sync($softwareData);
```

---

## Peringatan

> ⚠️ **PENTING**: Saat menambah software ke lab, pastikan data masuk ke **KEDUA TABEL**:
>
> - `inventories` untuk tracking inventaris
> - `lab_software` untuk counting dan matching

Jika hanya mengisi salah satu, akan terjadi inkonsistensi data!
