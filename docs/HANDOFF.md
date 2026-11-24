# Handoff: Absensi DJKI

Terakhir diperbarui: 24 November 2026.

## Login
- Admin: nama/username + password. Nama diingat lewat cookie, login balik cukup password. Ada taut "Ganti".
- Siswa: Nama Lengkap / No HP + password. Nama cocok tanpa spasi dan tanpa peduli huruf besar.
- Password lama yang tersimpan apa adanya tetap bisa dipakai. Password baru (tambah siswa, ubah password) disimpan sebagai hash bcrypt lewat `password_cocok()` dan `password_siap_simpan()` di `config/database.php`.

## Git
- `origin` = https://github.com/DiGitdaw/AbsensiPKL.git (public, branch `main`). Push/pull harian memakai remote ini.
- Repo lama `Fatahsahaja/AbsensiPKL` tidak dipakai lagi.
- Riwayat 8 commit dari sumber awal tetap ada di Git sebagai catatan asal kode (MIT) dan tidak dihapus.
- `.gitignore` menahan `/uploads/bukti/`, `/uploads/profile/`, `/uploads/siswa/` dan file sementara.
- 37 foto contoh lama sudah dihapus; yang tersisa hanya foto admin dan 3 bukti absensi yang masih dirujuk database.

## Tema
- Terang ala dgip.go.id: putih, biru `#2E77BC`, kuning emas, navy `#1F2A44`.
- Overlay `assets/css/theme.css`. Kartu pilihan yang aktif memakai latar solid `#EFF6FF`, bukan transparan.
- Popup `datalist` yang hitam di form tambah sudah diganti select + opsi "Lainnya...". Form edit siswa memakai select yang sama, dengan grup SMK/Mahasiswa dan tanpa duplikat. Filter sekolah di Data Siswa dan Absensi juga pakai select + Lainnya.

## Form Tambah Siswa
- Satu card, bukan card terpisah.
- Urutan: Jenis Pendaftar (SMK/Mahasiswa), **Jumlah Siswa**, lalu data.
- Jumlah 1: field Nama Lengkap. Jumlah lebih dari 1: textarea daftar nama, satu per baris.
- Sekolah dan kelas memakai select + "Lainnya...". Isian sekolah, kelas, jurusan, dan PKL diingat dari penambahan terakhir (`$_SESSION['last_input_siswa']`), jadi tidak perlu mengetik ulang.
- Password satu untuk semua saat tambah massal. Siswa bisa mengganti password sendiri di Profil.
- Foto profil hanya untuk tambah satuan.

## Profil siswa
- NIS dan Email dihapus dari tampilan dan form.
- Jurusan tampil lengkap dengan singkatan, misal `TEKNIK PERAWATAN GEDUNG (TKP)`.
- Ubah password tersedia.

## Kartu sekolah
- Data Absensi: `hadir/total` per sekolah, badge merah untuk pending, klik untuk filter. Maksimal 3 kartu + "Lihat semua sekolah". Kartu memakai parameter `sekolah_pilihan`, sama seperti filter, jadi daftar hanya berisi siswa dari sekolah yang diklik.
- Data Siswa: jumlah siswa PKL per sekolah + "Lihat semua sekolah". Klik kartu memfilter tepat ke sekolah itu.

## Profil admin
- Email diganti No HP. Halaman siswa `siswa/pembimbing/` menampilkan nama, foto, No HP.

## Migration
Sudah diterapkan ke `absensi_siswa`:
1. `2026-11-12_jurusan_varchar.sql`
2. `2026-11-15_siswa_profil.sql`
3. `2026-11-16_siswa_nis_email_nullable.sql`
4. `2026-11-16_pengaturan.sql`
5. `2026-11-18_sekolah.sql`
6. `2026-11-20_admin_no_hp.sql`
7. `2026-11-20_admin_email_nullable.sql`

## Belum dikerjakan
- Approve dari kartu sekolah / badge notifikasi.
- Aktivitas Terkini bisa diklik untuk menyetujui.
- PKL berkelompok dengan hitung laki-laki/perempuan.
- Navigasi angkatan PKL sebelumnya dengan ringkasan riwayat.

## Catatan
- NIS dan Email tidak lagi tampil di Data Siswa, detail/daftar Absensi, maupun Laporan/export. Kolom yang dipakai adalah No HP dan Sekolah.
- Tampilan mobile belum diukur dengan browser nyata.

## Cara lanjut
Baca file ini dulu di sesi baru, lalu sebutkan mau dikerjakan apa.
