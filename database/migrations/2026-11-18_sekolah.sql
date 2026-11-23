-- Nama sekolah asal siswa, dipakai untuk kartu dan pencarian per sekolah.
ALTER TABLE `siswa`
  ADD COLUMN `sekolah` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL AFTER `kelas`;
