# Panduan Setup Project SIOPAL

## Prasyarat

- PHP 8.2+
- Composer
- Node.js 18+
- MySQL 8.0+

---

## 1. Clone Project

```bash
git clone <repository-url> SIOPAL-UDINUS2
cd SIOPAL-UDINUS2
```

---

## 2. Install Dependencies

```bash
# PHP dependencies
composer install

# Node dependencies
npm install
```

---

## 3. Konfigurasi Environment

```bash
# Copy environment file
cp .env.example .env

# Generate app key
php artisan key:generate
```

Edit `.env` dan sesuaikan:

```env
DB_DATABASE=siopal
DB_USERNAME=root
DB_PASSWORD=your_password
```

---

## 4. Setup Database

```bash
# Jalankan migrasi
php artisan migrate
```

---

## 5. Setup Roles & Permissions (PENTING!)

> ⚠️ **JANGAN modifikasi folder `vendor/`!** Semua konfigurasi sudah di-publish ke `config/filament-shield.php`

```bash
# Seed roles dan permissions terlebih dahulu
php artisan db:seed --class=RolePermissionSeeder

# Seed super admin user
php artisan db:seed --class=UserSeeder
```

**Login Credentials:**
| Email | Password | Role |
|-------|----------|------|
| superadmin@mail.com | superadmin | super_admin |

---

## 6. Jalankan Seeder Data Master

**URUTAN PENTING!** Jalankan seeder sesuai urutan berikut:

```bash
# 1. Roles & User (HARUS PERTAMA)
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=UserSeeder

# 2. Data dasar
php artisan db:seed --class=ProdiSeeder          # Program Studi
php artisan db:seed --class=LaboratoriumSeeder   # Data Lab

# 3. Data Software
php artisan db:seed --class=SoftwareDetailSeeder # Master software (41 items)

# 4. Data Mata Kuliah
php artisan db:seed --class=CourseSeeder         # 34 mata kuliah

# 5. Relasi Course-Software
php artisan db:seed --class=CourseSoftwareSeeder # Link matkul ke software

# 6. Relasi Lab-Software (inventaris + pivot)
php artisan db:seed --class=LabSoftwareSeeder    # 190 inventaris + pivot data

# 7. Data tambahan (opsional)
php artisan db:seed --class=TimeSlotSeeder       # Slot waktu
php artisan db:seed --class=ScheduleSeeder       # Jadwal contoh
```

### Atau jalankan semua sekaligus:

```bash
php artisan migrate:fresh --seed
```

---

## 7. Build Assets & Run Server

```bash
# Build frontend assets
npm run build

# Jalankan server development
php artisan serve
```

Akses aplikasi di: `http://localhost:8000/admin`

---

## Seeder Summary

| Seeder                 | Deskripsi            | Jumlah Data            |
| ---------------------- | -------------------- | ---------------------- |
| `SoftwareDetailSeeder` | Master software      | 41 software            |
| `CourseSeeder`         | Mata kuliah          | 34 matkul              |
| `CourseSoftwareSeeder` | Link matkul-software | 26 matkul linked       |
| `LabSoftwareSeeder`    | Inventaris & pivot   | 190 inventaris, 12 lab |

---

## Troubleshooting

### Error: "Class not found"

```bash
composer dump-autoload
```

### Error: "Table already exists"

```bash
php artisan migrate:fresh  # HATI-HATI: Hapus semua data!
```

### Shield tidak generate permission

```bash
php artisan shield:generate --all --option=permissions
```

---

## Dokumentasi Terkait

- [Arsitektur Data Software](./ARSITEKTUR_DATA_SOFTWARE.md) - Penjelasan tabel `lab_software` vs `inventories`
