-- Ubah kolom jurusan dari enum tetap menjadi teks bebas,
-- supaya jurusan bisa diketik manual di form siswa.
-- Jalankan sekali pada database absensi_siswa yang sudah ada.

ALTER TABLE `siswa`
  MODIFY `jurusan` varchar(50) COLLATE utf8mb4_general_ci NOT NULL;
