-- NIS dan email tidak lagi wajib untuk siswa baru.
-- Kolom dipertahankan agar data lama tetap terbaca.

ALTER TABLE `siswa`
  MODIFY `nis` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  MODIFY `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL;
