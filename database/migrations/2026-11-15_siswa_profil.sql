-- Tambah data pendukung mode SMK/Mahasiswa dan kelas teks bebas.
-- Jalankan sekali pada database absensi_siswa yang sudah ada.

ALTER TABLE `siswa`
  MODIFY `kelas` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  ADD COLUMN `jenis` enum('smk','mahasiswa') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'smk',
  ADD COLUMN `jenis_kelamin` enum('L','P') COLLATE utf8mb4_general_ci DEFAULT NULL,
  ADD COLUMN `no_hp` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  ADD COLUMN `pkl_mulai` date DEFAULT NULL,
  ADD COLUMN `pkl_selesai` date DEFAULT NULL;
