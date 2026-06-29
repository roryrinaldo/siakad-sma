# Sistem Informasi Akademik SMA

MVP Sistem Informasi Akademik tingkat SMA berbasis Laravel, Blade, Tailwind CSS, session auth, Spatie Laravel Permission, dan DomPDF.

## Fitur MVP

- Login/logout, ubah profil, ubah password, register publik dinonaktifkan.
- Role: Admin, Kepala Sekolah, Guru, Wali Kelas, Siswa, Orang Tua.
- Dashboard role-based dengan statistik akademik, jadwal hari ini, dan pengumuman.
- CRUD master: siswa, guru, kelas, mata pelajaran, tahun ajaran, semester.
- Jadwal pelajaran dengan validasi bentrok guru dan kelas.
- Absensi siswa, input nilai otomatis menghitung nilai akhir, raport digital sederhana.
- Pengumuman umum/role/kelas.
- Laporan siswa, guru, absensi, nilai, dan raport dalam PDF serta CSV yang bisa dibuka Excel.

## Kebutuhan

- PHP 8.3+
- Composer
- Node.js 22+
- MySQL 8+ untuk produksi/presentasi, atau SQLite untuk pengembangan cepat

## Instalasi

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Atur database di `.env`.

Contoh MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=siakad_sma
DB_USERNAME=root
DB_PASSWORD=
```

Contoh SQLite:

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

Jika memakai SQLite, buat file database lebih dulu:

```bash
touch database/database.sqlite
```

## Migrasi dan Seeder

```bash
php artisan migrate --seed
npm run build
php artisan serve
```

Aplikasi berjalan di `http://127.0.0.1:8000`.

## Akun Default

Semua akun memakai password:

```text
password
```

Daftar akun:

- Admin: `admin@sia.test`
- Kepala Sekolah: `kepsek@sia.test`
- Guru: `guru@sia.test`
- Wali Kelas: `wali@sia.test`
- Siswa: `siswa@sia.test`
- Orang Tua: `ortu@sia.test`

## Catatan Teknis

- Role dan permission memakai Spatie Laravel Permission.
- PDF memakai `barryvdh/laravel-dompdf`.
- Export Excel MVP menggunakan CSV karena paket Laravel Excel yang kompatibel dengan project ini ter-resolve ke versi lama berbasis `PHPExcel` yang abandoned dan memiliki advisory keamanan.
- Vite tidak memakai font remote agar build tidak membutuhkan koneksi eksternal selain instalasi package.

## Pengujian

```bash
php artisan test
```

Test mencakup redirect entrypoint, login admin default, halaman inti, export CSV, dan export PDF.
