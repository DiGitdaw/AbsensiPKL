# Absensi DJKI

Aplikasi web untuk mengelola kehadiran peserta PKL/magang, baik dari SMK maupun mahasiswa. Pembimbing (admin) mengelola data peserta dan menyetujui absensi, sedangkan peserta mengajukan absensi lewat portal siswa.

Tema antarmuka memakai palet biru `#2E77BC`, emas, dan navy `#1F2A44` dengan latar terang.

## Fitur

### Pembimbing (admin)

- Login dengan nama/username dan password. Nama diingat selama 30 hari, sehingga login berikutnya cukup mengisi password.
- Dashboard dengan ringkasan kehadiran, grafik, dan kartu **Jadwal PKL** yang berlaku untuk semua peserta.
- Kartu per sekolah menampilkan jumlah hadir/total peserta dan jumlah pengajuan yang menunggu persetujuan. Maksimal tiga kartu ditampilkan, sisanya lewat tombol "Lihat semua sekolah".
- Manajemen data peserta: tambah satuan atau **tambah massal per sekolah** dengan satu password kelompok. Nama sekolah, kelas, jurusan, dan tanggal PKL diingat dari input terakhir.
- Pilihan jurusan berupa kartu kode (misalnya `RPL`, `TKP`) dengan nama lengkap di bawahnya, plus opsi "Lainnya" untuk input manual.
- Tanggal mulai dan selesai PKL bisa diubah untuk kebutuhan reschedule.
- Filter sekolah, jenis peserta, kelas, dan jurusan pada halaman Absensi, Data Siswa, dan Laporan.
- Persetujuan atau penolakan pengajuan absensi beserta bukti foto.
- Ekspor laporan ke PDF dan Excel.
- Profil pembimbing memakai nomor HP sebagai kontak, yang juga tampil di halaman pembimbing portal siswa.
- Log aktivitas seluruh tindakan pengguna.

### Peserta (siswa/mahasiswa)

- Login memakai nama lengkap atau nomor HP dan password yang diberikan pembimbing. Nama tidak peduli spasi dan huruf besar.
- Mengajukan absensi dengan keterangan dan bukti foto.
- Melihat riwayat absensi dan status persetujuannya.
- Mengubah nama, foto profil, dan password sendiri. Password kelompok bisa diganti setelah akun pertama dipakai.
- Melihat profil pembimbing beserta nomor HP untuk dihubungi.

## Teknologi

- PHP 8.0+ dengan PDO
- MySQL 8.0
- HTML, CSS, JavaScript, Tailwind CSS (CDN), Font Awesome
- Dompdf untuk PDF dan PhpSpreadsheet untuk Excel

## Instalasi

1. Clone repository ini:

   ```bash
   git clone https://github.com/FatahSahaja/AbsensiPKL.git
   cd AbsensiPKL
   ```

2. Buat database MySQL:

   ```sql
   CREATE DATABASE absensi_siswa;
   ```

3. Import `database/database.sql`. File ini berisi struktur terbaru dan satu akun admin awal, tanpa data peserta.

   ```bash
   mysql -u root absensi_siswa < database/database.sql
   ```

4. Sesuaikan koneksi database di `config/database.php` (`$db_host`, `$db_name`, `$db_user`, `$db_pass`).

5. Pastikan folder `uploads/` bisa ditulis oleh web server. Subfolder dibuat otomatis saat ada unggahan.

   ```bash
   mkdir -p uploads/admin uploads/bukti uploads/profile uploads/siswa
   chmod 755 uploads
   ```

6. Buka aplikasi:

   ```
   http://localhost/AbsensiPKL
   ```

## Akun awal

- Admin: username `admin`, password `admin123`, nama `Administrator`, nomor HP `081234567890`.
- Ganti password admin setelah login pertama lewat halaman Profil.
- Akun peserta dibuat oleh pembimbing lewat menu Data Siswa → Tambah Siswa. Tidak ada akun siswa bawaan.

Jika database sudah dibuat dari versi lama, jalankan berkas-berkas di `database/migrations/` secara berurutan untuk menyesuaikan struktur.

## Struktur singkat

```
admin/           halaman pembimbing (dashboard, absensi, siswa, laporan, profil)
siswa/           halaman peserta (dashboard, riwayat, profil, pembimbing)
config/          koneksi database, jurusan, dan helper aplikasi
database/        struktur database dan migrasi
assets/          logo, CSS tema, dan aset default
uploads/         foto unggahan (tidak ikut ke repository)
docs/            catatan pengembangan
```

## Kredit dan lisensi

Bermula dari basis aplikasi absensi siswa open-source berlisensi MIT yang kemudian dirombak besar pada antarmuka, alur login, manajemen peserta, dan fitur PKL. Informasi lisensi asli tetap tercantum di [LICENSE](LICENSE).

Proyek ini didistribusikan di bawah [MIT License](LICENSE).
