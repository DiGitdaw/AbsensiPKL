-- Profil admin memakai No HP, email tidak lagi diutamakan.
ALTER TABLE `admin`
  ADD COLUMN `no_hp` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL AFTER `nama_lengkap`;
