# Sistem Informasi Akademik SMA

MVP Sistem Informasi Akademik tingkat SMA berbasis Laravel, Blade, Tailwind CSS, session auth manual, Spatie Laravel Permission, dan DomPDF.

## Fitur MVP

- Auth manual: login, logout, ubah profil, ubah password. Register publik tidak disediakan.
- Role: Admin, Kepala Sekolah, Guru, Wali Kelas, Siswa, Orang Tua.
- Manajemen user dan role oleh Admin, termasuk koneksi akun ke siswa, guru, dan anak untuk Orang Tua.
- CRUD master: siswa, guru, kelas, mata pelajaran, tahun ajaran, semester.
- Import CSV untuk data siswa dan guru.
- Dashboard role-based.
- Jadwal pelajaran dengan validasi bentrok guru dan kelas.
- Absensi siswa per siswa atau input massal per jadwal/kelas.
- Input nilai per siswa atau massal per jadwal/kelas, otomatis menghitung nilai akhir.
- Raport digital, generate massal per kelas/tahun ajaran/semester, validasi wali kelas, dan PDF per siswa.
- Rekap akademik kelas dengan export PDF/CSV.
- Pengumuman umum, role, dan kelas.
- Laporan siswa, guru, absensi, nilai, dan raport dalam PDF serta CSV kompatibel Excel dengan filter.

## Batasan

- Tidak ada register publik.
- Tidak ada SPP, PPDB, atau e-learning kompleks.
- Export spreadsheet memakai CSV kompatibel Excel, bukan file `.xlsx` native.
- Import memakai CSV, bukan `.xlsx` native.

## Kebutuhan

- PHP 8.3+
- Composer
- Node.js 22+
- MySQL 8+ untuk presentasi/produksi, atau SQLite untuk pengembangan cepat

## Instalasi Windows/Laragon

Gunakan terminal di folder project:

```powershell
cd C:\laragon\www\siakad-sma
composer install
npm install
copy .env.example .env
php artisan key:generate
```

Jika `php` belum ada di PATH, gunakan PHP Laragon langsung:

```powershell
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' artisan key:generate
```

Contoh `.env` MySQL Laragon:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=siakad_sma
DB_USERNAME=root
DB_PASSWORD=
```

Buat database `siakad_sma` di MySQL/phpMyAdmin, lalu jalankan:

```powershell
php artisan migrate --seed
npm run build
php artisan serve
```

Aplikasi berjalan di `http://127.0.0.1:8000`.

## Instalasi SQLite Opsional

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

Windows PowerShell:

```powershell
New-Item -ItemType File -Force database\database.sqlite
php artisan migrate --seed
```

## Akun Default

Semua akun memakai password:

```text
password
```

- Admin: `admin@sia.test`
- Kepala Sekolah: `kepsek@sia.test`
- Guru: `guru@sia.test`
- Wali Kelas: `wali@sia.test`
- Siswa: `siswa@sia.test`
- Orang Tua: `ortu@sia.test`

## Testing

```powershell
php artisan test
```

Test mencakup login admin default, akses dashboard, isolasi data role, input massal, import CSV, export PDF, dan export CSV kompatibel Excel.

## Catatan Teknis

- Role dan permission memakai Spatie Laravel Permission.
- PDF memakai `barryvdh/laravel-dompdf`.
- CSV dipakai sebagai pengganti Excel native agar aman dari dependency Excel lama yang abandoned.
- Vite tidak memakai font remote agar build tidak membutuhkan fetch font eksternal.
